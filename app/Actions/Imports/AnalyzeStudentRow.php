<?php

namespace App\Actions\Imports;

use App\Enums\EstablishmentSector;
use App\Enums\StudentImportRowStatus as RowStatus;
use App\Enums\TrainingType;
use App\Http\Requests\Students\CreateStudentRequest;
use App\Http\Requests\Students\StudentInput;
use App\Models\Domain;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentIdentityLocks;
use App\Support\StudentImportColumns as Columns;
use App\Support\StudentNormalization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;

class AnalyzeStudentRow
{
    private Establishment $establishment;

    /** @var Collection<int,Level> */
    private Collection $levels;

    /** @var Collection<int,Mention> */
    private Collection $mentions;

    /** @var Collection<int,Program> */
    private Collection $programs;

    /** @var Collection<int,Domain> */
    private Collection $domains;

    /** @var Collection<int,Student> */
    private Collection $students;

    /** @var Collection<int,StudentRecord> */
    private Collection $records;

    /** @var array<string,int> request reference => student id, for this establishment and year */
    private array $references;

    public function __construct(private StudentImport $import, private User $actor, bool $lock = false)
    {
        $this->establishment = Establishment::query()->findOrFail($import->establishment_id);
        $this->levels = Level::query()->when($lock, fn ($q) => $q->sharedLock())->get();
        $this->mentions = Mention::query()->where('establishment_id', $import->establishment_id)->when($lock, fn ($q) => $q->sharedLock())->get();
        $this->programs = Program::query()->where('establishment_id', $import->establishment_id)->when($lock, fn ($q) => $q->sharedLock())->get();
        $this->domains = Domain::query()->get();
        $this->references = StudentRecord::query()->where('academic_year_id', $import->academic_year_id)->where('establishment_id', $import->establishment_id)
            ->whereNotNull('scholarship_request_reference')->when($lock, fn ($q) => $q->lockForUpdate())
            ->pluck('student_id', 'scholarship_request_reference')->mapWithKeys(fn ($id, $reference) => [Columns::fold((string) $reference) => (int) $id])->all();
        $this->students = new Collection;
        $this->records = new Collection;
    }

    /** @param array<int,array<string,mixed>> $batch */
    public function loadCandidates(array $batch, bool $lock = false): void
    {
        $this->students = Student::query()->where(function ($query) use ($batch) {
            foreach (StudentIdentityLocks::UNIQUE_FIELDS as $field) {
                $values = array_values(array_filter(array_map(fn ($data) => StudentIdentityLocks::uniqueValues($data)[$field] ?? null, $batch)));
                if ($values !== []) {
                    $query->orWhereIn($field, $values);
                }
            }
            $identities = array_filter($batch, fn ($data) => filled($data['birth_date'] ?? null) && filled($data['last_name'] ?? null));
            if ($identities !== []) {
                $query->orWhere(fn ($q) => $q->whereIn('last_name', array_column($identities, 'last_name'))->whereIn('birth_date', array_merge(array_column($identities, 'birth_date'), array_map(fn ($date) => $date.' 00:00:00', array_column($identities, 'birth_date')))));
            }
            $query->orWhereRaw('1 = 0');
        })->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $this->records = StudentRecord::query()->whereIn('student_id', $this->students->modelKeys())->when($lock, fn ($q) => $q->lockForUpdate())->get();
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function handle(array $data): array
    {
        $errors = [];
        $warnings = [];
        $issue = fn (string $field, string $code, string $message) => ['field' => $field, 'code' => $code, 'message' => $message];
        foreach ($data as $field => $value) {
            if (is_string($value) && str_starts_with(ltrim($value), '=')) {
                $errors[] = $issue($field, 'formula', 'Les formules ne sont pas acceptées.');
            }
        }
        // Reuse the manual entry rules and French messages.
        $request = new CreateStudentRequest;
        $rules = $request->importIdentityRules();
        $validator = Validator::make($data, [...$rules, ...StudentInput::declarationRules()], $request->messages(), $request->attributes());
        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = $issue($field, 'invalid_'.$field, $message);
            }
        }
        if (empty($errors) && filled($data['birth_date'] ?? null) && filled($data['cin_issued_at'] ?? null) && $data['cin_issued_at'] < $data['birth_date']) {
            $errors[] = $issue('cin_issued_at', 'before_birth', 'La délivrance de la CIN précède la naissance.');
        }
        array_push($errors, ...$this->checkReferentials($data, $issue));
        $reference = filled($data['scholarship_request_reference'] ?? null) ? Columns::fold((string) $data['scholarship_request_reference']) : null;

        $matches = $this->students->filter(function (Student $student) use ($data) {
            if (filled($data['cin'] ?? null) && StudentNormalization::isStandardCin((string) $data['cin']) && $student->cin === $data['cin']) {
                return true;
            }

            return filled($data['birth_date'] ?? null) && $student->birth_date?->format('Y-m-d') === $data['birth_date'] && Columns::fold($student->last_name) === Columns::fold((string) ($data['last_name'] ?? '')) && Columns::fold((string) $student->first_names) === Columns::fold((string) ($data['first_names'] ?? ''));
        });
        $student = $matches->count() === 1 ? $matches->first() : null;
        // Phone and email are unique: owned by another identity, they are an error, not a match.
        foreach (['phone' => 'Ce numéro de téléphone', 'email' => 'Cette adresse mail'] as $field => $label) {
            if (filled($data[$field] ?? null) && $this->students->contains(fn (Student $other) => $other->getAttribute($field) === $data[$field] && ! $matches->contains($other))) {
                $errors[] = $issue($field, 'already_used', $label.' est déjà utilisé(e) par un autre étudiant.');
            }
        }
        if ($reference !== null && isset($this->references[$reference]) && $this->references[$reference] !== $student?->id) {
            $errors[] = $issue('scholarship_request_reference', 'reference_used', 'Cette référence de demande de bourse est déjà attribuée dans l’établissement pour cette année.');
        }
        $record = null;
        $duplicate = false;
        if ($matches->count() > 1) {
            $warnings[] = $issue('identity', 'ambiguous_identity', 'Plusieurs identités possibles : aucune fusion automatique.');
        }
        if ($student !== null) {
            $owned = $this->records->where('student_id', $student->id);
            if ($this->actor->hasRole('establishment') && ($owned->where('establishment_id', $this->import->establishment_id)->isEmpty() || $owned->where('establishment_id', '!=', $this->import->establishment_id)->isNotEmpty())) {
                $warnings[] = $issue('identity', 'restricted_identity', 'Cette identité nécessite une vérification par la Scolarité Centrale.');
                $student = null;
            } else {
                if (! $student->is_active) {
                    $warnings[] = $issue('identity', 'inactive_student', 'Cette identité est inactive.');
                }
                foreach (array_keys($rules) as $field) {
                    $old = $student->getAttribute($field);
                    $old = match (true) {
                        $old instanceof \DateTimeInterface => $old->format('Y-m-d'),
                        $old instanceof \BackedEnum => $old->value,
                        default => $old,
                    };
                    if (filled($old) && filled($data[$field] ?? null) && (string) $old !== (string) $data[$field]) {
                        $warnings[] = $issue($field, 'official_conflict', 'La valeur fournie diffère de la valeur officielle : aucune modification automatique.');
                    }
                }
                $birth = $student->birth_date?->format('Y-m-d') ?? ($data['birth_date'] ?? null);
                $issued = $student->cin_issued_at?->format('Y-m-d') ?? ($data['cin_issued_at'] ?? null);
                if (filled($birth) && filled($issued) && $issued < $birth) {
                    $warnings[] = $issue('cin_issued_at', 'merged_before_birth', 'Les dates proposées sont incompatibles avec les dates officielles.');
                }
                $record = $owned->where('academic_year_id', $this->import->academic_year_id)->where('establishment_id', $this->import->establishment_id)->first();
                if ($record !== null) {
                    if ($this->sameDeclaration($record, $data)) {
                        $duplicate = true;
                    } else {
                        $warnings[] = $issue('record', 'record_conflict', 'Une déclaration de cette année existe avec d’autres valeurs (niveau, parcours, code ou demande de bourse).');
                    }
                }
                if ($owned->where('academic_year_id', $this->import->academic_year_id)->where('establishment_id', '!=', $this->import->establishment_id)->contains(fn ($r) => $r->status->value === 'active')) {
                    $warnings[] = $issue('record', 'foreign_active_record', 'Une déclaration active existe dans un autre établissement. Aucun transfert automatique.');
                }
            }
        }
        $status = $errors !== [] ? RowStatus::Rejected : ($warnings !== [] ? RowStatus::Warning : ($duplicate ? RowStatus::Duplicate : RowStatus::Valid));

        return ['normalized_data' => $data, 'status' => $status, 'errors' => $errors, 'warnings' => $warnings, 'matched_student_id' => $student?->id, 'matched_student_record_id' => $record?->id];
    }

    /**
     * Level, mention and program resolved in the establishment of the import; the descriptive
     * columns (school, sector, domain, training type) must agree with the referentials.
     * Sets level_id and program_id on the data.
     *
     * @param  array<string,mixed>  $data
     * @param  callable(string,string,string): array<string,string>  $issue
     * @return list<array<string,string>>
     */
    private function checkReferentials(array &$data, callable $issue): array
    {
        $errors = [];
        $level = $this->resolve($this->levels->all(), $data['level'] ?? null);
        if ($level === null || ! $level->is_active) {
            $errors[] = $issue('level', 'unknown_or_ambiguous', 'Le niveau est absent, inconnu, ambigu ou inactif.');
        }
        $mention = null;
        if (filled($data['mention'] ?? null)) {
            $mention = $this->resolve($this->mentions->all(), $data['mention']);
            if ($mention === null || ! $mention->is_active) {
                $errors[] = $issue('mention', 'unknown_or_ambiguous', 'La mention est inconnue, ambiguë, inactive ou appartient à un autre établissement.');
            }
        }
        $program = null;
        if (filled($data['program'] ?? null)) {
            // A program name (e.g. "Tronc Commun") is only unique inside its mention.
            $candidates = $mention === null ? $this->programs->all() : $this->programs->where('mention_id', $mention->id)->all();
            $program = $this->resolve($candidates, $data['program']);
            if ($program === null || ! $program->is_active) {
                $errors[] = $issue('program', 'unknown_or_ambiguous', $mention === null ? 'Le parcours est inconnu, ambigu, inactif ou appartient à un autre établissement.' : 'Le parcours est inconnu, ambigu ou inactif dans cette mention.');
            }
        } elseif ($mention !== null) {
            $errors[] = $issue('program', 'missing_program', 'Le parcours est obligatoire lorsque la mention est renseignée.');
        }
        if (filled($data['establishment'] ?? null) && ! $this->namesEstablishment((string) $data['establishment'])) {
            $errors[] = $issue('establishment', 'other_establishment', 'L’école ou institut du fichier ne correspond pas à l’établissement de l’import.');
        }
        if (filled($data['sector'] ?? null)) {
            $sector = $this->enumFromLabel(EstablishmentSector::cases(), (string) $data['sector']);
            if ($sector === null || ($this->establishment->sector !== null && $sector !== $this->establishment->sector)) {
                $errors[] = $issue('sector', 'sector_mismatch', 'L’institution (publique/privée) est inconnue ou ne correspond pas à l’établissement.');
            }
        }
        if (filled($data['domain'] ?? null) && $mention?->domain_id !== null) {
            $domain = $this->domains->firstWhere('id', $mention->domain_id);
            if ($domain === null || ! $this->namesDomain($domain, (string) $data['domain'])) {
                $errors[] = $issue('domain', 'domain_mismatch', 'Le domaine ne correspond pas au domaine de la mention.');
            }
        }
        if (filled($data['training_type'] ?? null)) {
            $type = $this->enumFromLabel(TrainingType::cases(), (string) $data['training_type']);
            if ($type === null || ($program?->training_type !== null && $type !== $program->training_type)) {
                $errors[] = $issue('training_type', 'training_type_mismatch', 'Le type de formation est inconnu ou ne correspond pas au parcours.');
            }
        }
        $data['level_id'] = $level?->id;
        $data['program_id'] = $program?->id;

        return $errors;
    }

    /** @param array<string,mixed> $data */
    private function sameDeclaration(StudentRecord $record, array $data): bool
    {
        return $record->level_id === $data['level_id'] && $record->program_id === $data['program_id']
            && $record->repeat_code?->value === ($data['repeat_code'] ?? null)
            && $record->scholarship_requested_at?->format('Y-m-d') === ($data['scholarship_requested_at'] ?? null)
            && Columns::fold((string) $record->scholarship_request_reference) === Columns::fold((string) ($data['scholarship_request_reference'] ?? ''));
    }

    /** Name, code, "Name (CODE)" or "CODE - Name" of the establishment of the import. */
    private function namesEstablishment(string $value): bool
    {
        $folded = Columns::fold($value);
        $name = Columns::fold($this->establishment->name);
        $code = Columns::fold($this->establishment->code);
        preg_match('/\(([^)]*)\)\s*$/', $folded, $parts);

        return in_array($folded, [$name, $code], true) || str_starts_with($folded, $name.' (') || (isset($parts[1]) && Columns::fold($parts[1]) === $code);
    }

    /** "(ALSH) Arts, Lettres et Sciences Humaines", "ALSH" or the domain name. */
    private function namesDomain(Domain $domain, string $value): bool
    {
        $folded = Columns::fold($value);
        if (preg_match('/^\(([^)]*)\)\s*(.*)$/', $folded, $parts) === 1) {
            return Columns::fold($parts[1]) === Columns::fold($domain->code);
        }

        return in_array($folded, [Columns::fold($domain->code), Columns::fold($domain->name)], true);
    }

    /**
     * @template T of EstablishmentSector|TrainingType
     *
     * @param  list<T>  $cases
     * @return T|null
     */
    private function enumFromLabel(array $cases, string $value): EstablishmentSector|TrainingType|null
    {
        $folded = Columns::fold($value);
        foreach ($cases as $case) {
            // Variants share the first letters: "Professionnel"/"Professionnalisant", "Public"/"Publique", "Privé"/"Privée".
            if (in_array($folded, [Columns::fold($case->label()), $case->value], true) || (strlen($folded) >= 5 && str_starts_with(Columns::fold($case->label()), substr($folded, 0, 5)))) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @template T of Level|Mention|Program
     *
     * @param  array<int,T>  $items
     * @return T|null
     */
    private function resolve(array $items, mixed $value): Level|Mention|Program|null
    {
        if (! is_string($value) || ! filled($value)) {
            return null;
        }
        $matches = array_filter($items, fn ($item) => filled($item->code) && Columns::fold($item->code) === Columns::fold($value));
        if ($matches === []) {
            $matches = array_filter($items, fn ($item) => Columns::fold($item->name) === Columns::fold($value));
        }

        return count($matches) === 1 ? array_values($matches)[0] : null;
    }
}
