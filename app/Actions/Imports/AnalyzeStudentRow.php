<?php

namespace App\Actions\Imports;

use App\Enums\EstablishmentSector;
use App\Enums\ImportKind;
use App\Enums\RepeatCode;
use App\Enums\SituationVerification;
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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AnalyzeStudentRow
{
    /** Contacts of the PNES base set aside when unusable, instead of rejecting the scholarship holder. */
    public const PNES_LENIENT = ['email', 'phone'];

    /** Identity texts compared without capitals, accents or spacing. */
    private const TEXT_FIELDS = ['last_name', 'first_names', 'mother_name', 'cin_issued_place', 'address'];

    /** @var Collection<int,Establishment> establishments the import may write to (one, or all for a global import) */
    private Collection $establishments;

    /** Establishment of the row being analysed. */
    private ?Establishment $establishment = null;

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

    /** @var array<int,string> */
    private array $trashedCodes = [];

    /** @var array<string,int> "establishment id|request reference" => student id, for the year */
    private array $references;

    public function __construct(private StudentImport $import, private User $actor, bool $lock = false)
    {
        // A global import (no establishment) reads the establishment of each row from the file.
        $this->establishments = Establishment::query()->when($import->establishment_id !== null, fn ($q) => $q->whereKey($import->establishment_id))->get();
        // Codes of deleted establishments: never recreated by an import.
        $this->trashedCodes = $import->establishment_id !== null ? [] : array_values(array_map(fn ($code) => strtoupper((string) $code), Establishment::onlyTrashed()->pluck('code')->all()));
        $ids = $this->establishments->modelKeys();
        $this->levels = Level::query()->when($lock, fn ($q) => $q->sharedLock())->get();
        $this->mentions = Mention::query()->whereIn('establishment_id', $ids)->when($lock, fn ($q) => $q->sharedLock())->get();
        $this->programs = Program::query()->whereIn('establishment_id', $ids)->when($lock, fn ($q) => $q->sharedLock())->get();
        $this->domains = Domain::query()->get();
        $this->references = StudentRecord::query()->where('academic_year_id', $import->academic_year_id)->whereIn('establishment_id', $ids)
            ->whereNotNull('scholarship_request_reference')->when($lock, fn ($q) => $q->lockForUpdate())
            ->get(['student_id', 'establishment_id', 'scholarship_request_reference'])
            ->mapWithKeys(fn (StudentRecord $record) => [$record->establishment_id.'|'.Columns::fold((string) $record->scholarship_request_reference) => $record->student_id])->all();
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
        // Recomputed on every analysis (also at commit, from the stored row).
        unset($data['_confirms_record']);
        $kind = $this->import->kind;
        if ($kind === ImportKind::Preinscription) {
            // New bacheliers: first year and new students unless the file says otherwise.
            $data['repeat_code'] ??= RepeatCode::New->value;
            $data['level'] ??= 'L1';
            if (filled($data['result'] ?? null) && ! in_array(Columns::fold((string) $data['result']), ['admis', 'admise', 'retenu', 'retenue', 'selectionne', 'selectionnee', 'oui'], true)) {
                $errors[] = $issue('result', 'not_admitted', 'Candidat non retenu à la préinscription : la ligne n’est pas importée.');
            }
        }
        $this->establishment = $this->resolveEstablishment($data, $issue, $errors);
        // Establishment of a national file still missing from the platform: created at confirmation.
        $newEstablishment = is_array($data['_new_establishment'] ?? null) ? $data['_new_establishment'] : null;
        $data['establishment_id'] = $this->establishment?->id;
        $data['_establishment_code'] = $this->establishment->code ?? $newEstablishment['code'] ?? null;
        $establishmentId = $this->establishment?->id;
        // Reuse the manual entry rules and French messages.
        $request = new CreateStudentRequest;
        $rules = $request->importIdentityRules();
        // Sex and nationality are required only when the file has the column.
        foreach (['sex', 'nationality'] as $optional) {
            if (! array_key_exists($optional, $data)) {
                $rules[$optional] = ['nullable', ...array_filter($rules[$optional], fn ($rule) => $rule !== 'required')];
            }
        }
        $validator = Validator::make($data, [...$rules, ...StudentInput::declarationRules(), 'preinscription_reference' => ['nullable', 'string', 'max:50']], $request->messages(), $request->attributes());
        foreach ($validator->errors()->messages() as $field => $messages) {
            // The national PNES base cannot be corrected here: an unusable contact is set aside, the scholarship holder is kept.
            if ($kind === ImportKind::Pnes && in_array($field, self::PNES_LENIENT, true)) {
                $this->drop($data, $field, 'format invalide');

                continue;
            }
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
            // National identifiers of the PNES base.
            foreach (['uin', 'scholarship_code'] as $key) {
                if (filled($data[$key] ?? null) && $student->getAttribute($key) === $data[$key]) {
                    return true;
                }
            }

            return filled($data['birth_date'] ?? null) && $student->birth_date?->format('Y-m-d') === $data['birth_date'] && Columns::fold($student->last_name) === Columns::fold((string) ($data['last_name'] ?? '')) && Columns::fold((string) $student->first_names) === Columns::fold((string) ($data['first_names'] ?? ''));
        });
        $student = $matches->count() === 1 ? $matches->first() : null;
        // Phone and email are unique: owned by another identity, they are an error, not a match.
        foreach (['phone' => 'Ce numéro de téléphone', 'email' => 'Cette adresse mail'] as $field => $label) {
            if (filled($data[$field] ?? null) && $this->students->contains(fn (Student $other) => $other->getAttribute($field) === $data[$field] && ! $matches->contains($other))) {
                if ($kind === ImportKind::Pnes) {
                    $this->drop($data, $field, 'appartient déjà à un autre étudiant');
                } else {
                    $errors[] = $issue($field, 'already_used', $label.' est déjà utilisé(e) par un autre étudiant.');
                }
            }
        }
        if ($reference !== null && isset($this->references[$establishmentId.'|'.$reference]) && $this->references[$establishmentId.'|'.$reference] !== $student?->id) {
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
                        // Names and places written with other capitals, accents or spaces are the same value.
                        if (in_array($field, self::TEXT_FIELDS, true) && implode(' ', self::words((string) $old)) === implode(' ', self::words((string) $data[$field]))) {
                            continue;
                        }
                        if (in_array($field, is_array($data['_identity_updates'] ?? null) ? $data['_identity_updates'] : [], true)) {
                            // Chosen in the preview: the file value will replace the record, if this actor may correct it.
                            if (! Gate::forUser($this->actor)->allows('update', $student)) {
                                $warnings[] = $issue($field, 'identity_update_forbidden', 'Votre rôle ne permet pas de modifier la fiche de cet étudiant : gardez la valeur de la fiche ou faites corriger la fiche.');
                            }

                            continue;
                        }
                        if ($kind === ImportKind::Pnes && in_array($field, self::PNES_LENIENT, true)) {
                            // The contact of the student's record is kept; the holder is not set aside for it.
                            $this->drop($data, $field, 'ne correspond pas à la fiche, celle-ci est conservée');

                            continue;
                        }
                        $warnings[] = $issue($field, 'official_conflict', 'Différent de la fiche déjà enregistrée : l’identité officielle n’est jamais modifiée par un import.');
                    }
                }
                $birth = $student->birth_date?->format('Y-m-d') ?? ($data['birth_date'] ?? null);
                $issued = $student->cin_issued_at?->format('Y-m-d') ?? ($data['cin_issued_at'] ?? null);
                if (filled($birth) && filled($issued) && $issued < $birth) {
                    $warnings[] = $issue('cin_issued_at', 'merged_before_birth', 'Les dates proposées sont incompatibles avec les dates officielles.');
                }
                $record = $establishmentId === null ? null : $owned->where('academic_year_id', $this->import->academic_year_id)->where('establishment_id', $establishmentId)->first();
                if ($record !== null && $kind === ImportKind::Establishment && $record->verification !== SituationVerification::Verified) {
                    // A valid match will be verified only when the establishment import is confirmed.
                    $data['_confirms_record'] = $record->id;
                } elseif ($record !== null && $kind === ImportKind::Pnes) {
                    // Already declared: the national base marks the situation as a scholarship holder.
                    if ($record->is_scholarship_holder && $this->sameDeclaration($record, $data)) {
                        $duplicate = true;
                    } else {
                        $data['_confirms_record'] = $record->id;
                    }
                } elseif ($record !== null) {
                    if ($this->sameDeclaration($record, $data)) {
                        $duplicate = true;
                    } else {
                        $warnings[] = $issue('record', 'record_conflict', 'Une situation de cette année existe avec d’autres valeurs (niveau, parcours, code ou demande de bourse).');
                    }
                }
                if ($establishmentId !== null && $owned->where('academic_year_id', $this->import->academic_year_id)->where('establishment_id', '!=', $establishmentId)->contains(fn ($r) => $r->status->value === 'active')) {
                    $warnings[] = $issue('record', 'foreign_active_record', 'Une situation active existe dans un autre établissement. Aucun transfert automatique.');
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
        $newEstablishment = is_array($data['_new_establishment'] ?? null) ? $data['_new_establishment'] : null;
        $level = $this->resolve($this->levels->all(), $data['level'] ?? null);
        $newLevel = null;
        if ($level === null && $this->matches($this->levels->all(), $data['level'] ?? null) === [] && Level::standardCode($data['level'] ?? null) !== null) {
            // Standard LMD level (L1 to M2) missing from the platform: created at confirmation.
            $newLevel = Level::standardCode($data['level']);
        } elseif ($level === null) {
            $errors[] = $issue('level', 'unknown_or_ambiguous', 'Niveau inconnu ou ambigu : créez-le dans Référentiels → Niveaux.');
        } elseif (! $level->is_active) {
            $errors[] = $issue('level', 'inactive', 'Le niveau existe mais il est inactif : réactivez-le dans les référentiels.');
        }
        // A mention or program missing from the establishment is created at confirmation, in that
        // establishment only; an inactive or ambiguous one is never duplicated.
        $mention = null;
        $newMention = null;
        if (filled($data['mention'] ?? null) && $this->establishment === null && $newEstablishment !== null) {
            $newMention = (string) preg_replace('/\s+/u', ' ', trim((string) $data['mention']));
        } elseif (filled($data['mention'] ?? null) && $this->establishment !== null) {
            $found = $this->matches($this->mentions->where('establishment_id', $this->establishment->id)->all(), $data['mention']);
            if (count($found) === 1) {
                $mention = $found[0];
                if (! $mention->is_active) {
                    $errors[] = $issue('mention', 'inactive', 'La mention existe mais elle est inactive : réactivez-la dans les référentiels.');
                }
            } elseif (count($found) > 1) {
                $errors[] = $issue('mention', 'ambiguous', 'Plusieurs mentions de l’établissement correspondent à ce nom ou code.');
            } else {
                $newMention = (string) preg_replace('/\s+/u', ' ', trim((string) $data['mention']));
            }
        }
        $program = null;
        $newProgram = null;
        if ($this->establishment === null && $newEstablishment !== null) {
            // New establishment: its mentions and programs are all new.
            if (filled($data['program'] ?? null)) {
                $newProgram = (string) preg_replace('/\s+/u', ' ', trim((string) $data['program']));
            } elseif ($newMention !== null) {
                $errors[] = $issue('program', 'missing_program', 'Le parcours est obligatoire lorsque la mention est renseignée.');
            }
        } elseif ($this->establishment === null) {
            // Unknown establishment: its mentions and programs cannot be resolved (already reported).
        } elseif (filled($data['program'] ?? null)) {
            // A program name (e.g. "Tronc Commun") is only unique inside its mention.
            $candidates = match (true) {
                $mention !== null => $this->programs->where('mention_id', $mention->id)->all(),
                $newMention !== null => [],
                default => $this->programs->where('establishment_id', $this->establishment->id)->all(),
            };
            $found = $this->matches($candidates, $data['program']);
            if (count($found) === 1) {
                $program = $found[0];
                if (! $program->is_active) {
                    $errors[] = $issue('program', 'inactive', 'Le parcours existe mais il est inactif : réactivez-le dans les référentiels.');
                }
            } elseif (count($found) > 1) {
                $errors[] = $issue('program', 'ambiguous', 'Plusieurs parcours de l’établissement correspondent : précisez la mention.');
            } else {
                $newProgram = (string) preg_replace('/\s+/u', ' ', trim((string) $data['program']));
            }
        } elseif ($mention !== null || $newMention !== null) {
            $errors[] = $issue('program', 'missing_program', 'Le parcours est obligatoire lorsque la mention est renseignée.');
        }
        if (filled($data['sector'] ?? null) && $this->establishment !== null) {
            $sector = $this->enumFromLabel(EstablishmentSector::cases(), (string) $data['sector']);
            if ($sector === null || ($this->establishment->sector !== null && $sector !== $this->establishment->sector)) {
                $errors[] = $issue('sector', 'sector_mismatch', 'L’institution (publique/privée) est inconnue ou ne correspond pas à l’établissement.');
            }
        }
        $newDomain = null;
        $mentionDomain = null;
        if (filled($data['domain'] ?? null) && $mention?->domain_id !== null) {
            $domain = $this->domains->firstWhere('id', $mention->domain_id);
            if ($domain === null || ! $this->namesDomain($domain, (string) $data['domain'])) {
                $errors[] = $issue('domain', 'domain_mismatch', 'Le domaine ne correspond pas au domaine de la mention.');
            }
        } elseif (filled($data['domain'] ?? null) && $newMention !== null) {
            // The new mention takes the domain of the file: an existing one, or "(CODE) Name" created with it.
            $mentionDomain = $this->domains->first(fn (Domain $domain) => $this->namesDomain($domain, (string) $data['domain']));
            if ($mentionDomain === null && preg_match('/^\(\s*([A-Za-z0-9._-]{1,30})\s*\)\s*(\S.*)$/u', trim((string) $data['domain']), $parts) === 1) {
                $newDomain = ['code' => strtoupper($parts[1]), 'name' => (string) preg_replace('/\s+/u', ' ', trim($parts[2]))];
            } elseif ($mentionDomain === null) {
                $errors[] = $issue('domain', 'unknown_domain', 'Domaine inconnu : indiquez-le sous la forme « (CODE) Nom », par exemple « (ALSH) Arts, Lettres et Sciences Humaines ».');
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
        // References to create at confirmation (kept with the row, read back by the commit).
        $data['_new_level'] = $newLevel;
        $data['_new_mention'] = $newMention;
        $data['_new_program'] = $newProgram;
        $data['_mention_domain_id'] = $mentionDomain?->id;
        $data['_new_domain'] = $newDomain;
        $data['_training_type'] = filled($data['training_type'] ?? null) ? $this->enumFromLabel(TrainingType::cases(), (string) $data['training_type'])?->value : null;

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

    /**
     * Establishment of the row. A fixed import checks that the file names it (code or name);
     * a global import finds it by code first ("Institution Code"), else by name ("École / Institut").
     *
     * @param  array<string,mixed>  $data
     * @param  callable(string,string,string): array<string,string>  $issue
     * @param  list<array<string,string>>  $errors
     */
    private function resolveEstablishment(array &$data, callable $issue, array &$errors): ?Establishment
    {
        unset($data['_new_establishment']);
        $code = filled($data['establishment_code'] ?? null) ? implode('', self::words((string) $data['establishment_code'])) : null;
        $name = filled($data['establishment'] ?? null) ? (string) $data['establishment'] : null;
        $byCode = fn (Establishment $establishment) => implode('', self::words($establishment->code)) === $code;
        if ($this->import->establishment_id !== null) {
            $establishment = $this->establishments->first();
            if ($establishment === null) {
                return null;
            }
            if ($code !== null && ! $byCode($establishment)) {
                $errors[] = $issue('establishment_code', 'other_establishment', 'Le code établissement du fichier ne correspond pas à l’établissement de l’import.');
            } elseif ($code === null && $name !== null && ! $this->namesEstablishment($establishment, $name)) {
                $errors[] = $issue('establishment', 'other_establishment', 'L’école ou institut du fichier ne correspond pas à l’établissement de l’import.');
            }

            return $establishment;
        }
        if ($code === null && $name === null) {
            $errors[] = $issue('establishment_code', 'missing_establishment', 'Établissement absent : renseignez le code établissement ou le nom de l’école ou institut.');

            return null;
        }
        $byName = fn (Establishment $establishment) => $this->namesEstablishment($establishment, (string) $name);
        $found = $code !== null ? $this->establishments->filter($byCode) : $this->establishments->filter($byName);
        if ($found->isEmpty() && $code !== null && $name !== null) {
            // Another code for a known establishment (« EDSPA » / « EDSP Antsohihy »): recognised by its name.
            $found = $this->establishments->filter($byName);
        }
        $rawCode = trim((string) ($data['establishment_code'] ?? ''));
        if ($found->isEmpty() && $this->import->kind !== ImportKind::Establishment && $name !== null && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,29}$/', $rawCode) === 1 && ! in_array(strtoupper($rawCode), $this->trashedCodes, true)) {
            // A national file (PNES, pre-registration) names an establishment the platform does not have yet: created at confirmation.
            $data['_new_establishment'] = ['code' => strtoupper($rawCode), 'name' => mb_substr((string) preg_replace('/\s+/u', ' ', trim($name)), 0, 255)];

            return null;
        }
        if ($found->count() !== 1) {
            $errors[] = $issue($code !== null ? 'establishment_code' : 'establishment', 'unknown_establishment', 'Établissement introuvable : aucun établissement de la plateforme ne porte ce code ou ce nom.');

            return null;
        }
        $establishment = $found->first();
        if (! $establishment->is_active) {
            $errors[] = $issue($code !== null ? 'establishment_code' : 'establishment', 'inactive_establishment', 'L’établissement existe mais il est inactif.');
        }

        return $establishment;
    }

    /**
     * Words of a label, without case, accents or punctuation.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        return array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/', Columns::fold($text)) ?: [])));
    }

    /**
     * The school/institute column names the establishment of the import: same words as its name
     * (order, accents, case and punctuation ignored, with or without the bracketed part), or its
     * acronym alone or among the bracketed words. "Ecole de Droit et Science Politique Antsohihy
     * (EDSP Antsohihy)" names "Ecole de Droit et Science Politique (Antsohihy)", acronym EDSP.
     */
    private function namesEstablishment(Establishment $establishment, string $value): bool
    {
        $words = fn (string $text): array => self::words($text);
        $same = function (array $a, array $b): bool {
            sort($a);
            sort($b);

            return $a !== [] && $a === $b;
        };
        $outside = fn (string $text): string => (string) preg_replace('/\([^)]*\)/u', ' ', $text);
        preg_match_all('/\(([^)]*)\)/u', $value, $brackets);
        $name = $establishment->name;
        $code = implode('', $words($establishment->code));

        return $same($words($value), $words($name))
            || $same($words($outside($value)), $words($outside($name)))
            || $same($words($outside($value)), $words($name))
            || $same($words($value), $words($outside($name)))
            || implode('', $words($value)) === $code
            // Acronym followed by a place (« EDSP Antsohihy »): the other words must belong to the name.
            || (in_array($code, $words($value), true) && count($words($value)) > 1 && array_diff($words($value), [$code], $words($name)) === [])
            // Acronym among the bracketed words: every other word must still belong to the name (no other city).
            || (in_array($code, $words(implode(' ', $brackets[1])), true)
                && array_diff([...$words($outside($value)), ...array_diff($words(implode(' ', $brackets[1])), [$code])], $words($name)) === []);
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
        $matches = $this->matches($items, $value);

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * Items named by the value: by code first, then by exact name (case and accents ignored).
     *
     * @template T of Level|Mention|Program
     *
     * @param  array<int,T>  $items
     * @return list<T>
     */
    private function matches(array $items, mixed $value): array
    {
        if (! is_string($value) || ! filled($value)) {
            return [];
        }
        $matches = array_filter($items, fn ($item) => filled($item->code) && Columns::fold($item->code) === Columns::fold($value));
        if ($matches === []) {
            $matches = array_filter($items, fn ($item) => Columns::fold($item->name) === Columns::fold($value));
        }

        return array_values($matches);
    }

    /**
     * Sets aside an unusable value of the PNES base and remembers it for the preview.
     *
     * @param  array<string,mixed>  $data
     */
    private function drop(array &$data, string $field, string $reason): void
    {
        $data[$field] = null;
        $data['_dropped'] = [...($data['_dropped'] ?? []), Columns::labels(ImportKind::Pnes)[$field].' : '.$reason];
    }
}
