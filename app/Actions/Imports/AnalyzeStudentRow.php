<?php

namespace App\Actions\Imports;

use App\Enums\StudentImportRowStatus as RowStatus;
use App\Http\Requests\Students\CreateStudentRequest;
use App\Models\Level;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentImportColumns as Columns;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;

class AnalyzeStudentRow
{
    /** @var Collection<int,Level> */
    private Collection $levels;

    /** @var Collection<int,Program> */
    private Collection $programs;

    /** @var Collection<int,Student> */
    private Collection $students;

    /** @var Collection<int,StudentRecord> */
    private Collection $records;

    public function __construct(private StudentImport $import, private User $actor, bool $lock = false)
    {
        $this->levels = Level::query()->when($lock, fn ($q) => $q->sharedLock())->get();
        $this->programs = Program::query()->where('establishment_id', $import->establishment_id)->when($lock, fn ($q) => $q->sharedLock())->get();
        $this->students = new Collection;
        $this->records = new Collection;
    }

    /** @param array<int,array<string,mixed>> $batch */
    public function loadCandidates(array $batch, bool $lock = false): void
    {
        $this->students = Student::query()->where(function ($query) use ($batch) {
            foreach (['student_number', 'cin', 'bac_registration_number'] as $field) {
                $values = array_filter(array_column($batch, $field), fn ($v) => filled($v));
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
        // Reuse the manual identity validation rules and French messages.
        $request = new CreateStudentRequest;
        $rules = $request->importIdentityRules();
        $validator = Validator::make($data, $rules, $request->messages(), $request->attributes());
        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = $issue($field, 'invalid_'.$field, $message);
            }
        }
        if (empty($errors) && filled($data['birth_date'] ?? null) && filled($data['cin_issued_at'] ?? null) && $data['cin_issued_at'] < $data['birth_date']) {
            $errors[] = $issue('cin_issued_at', 'before_birth', 'La délivrance de la CIN précède la naissance.');
        }
        $level = $this->resolve($this->levels->all(), $data['level'] ?? null);
        if ($level === null || ! $level->is_active) {
            $errors[] = $issue('level', 'unknown_or_ambiguous', 'Le niveau est absent, inconnu, ambigu ou inactif.');
        }
        $program = filled($data['program'] ?? null) ? $this->resolve($this->programs->all(), $data['program']) : null;
        if (filled($data['program'] ?? null) && ($program === null || ! $program->is_active)) {
            $errors[] = $issue('program', 'unknown_or_ambiguous', 'Le parcours est inconnu, ambigu, inactif ou appartient à un autre établissement.');
        }
        $data['level_id'] = $level?->id;
        $data['program_id'] = $program?->id;
        $matches = $this->students->filter(function (Student $student) use ($data) {
            foreach (['student_number', 'cin', 'bac_registration_number'] as $field) {
                if (filled($data[$field] ?? null) && Columns::fold((string) $student->getAttribute($field)) === Columns::fold((string) $data[$field])) {
                    return true;
                }
            }

            return filled($data['birth_date'] ?? null) && $student->birth_date?->format('Y-m-d') === $data['birth_date'] && Columns::fold($student->last_name) === Columns::fold((string) ($data['last_name'] ?? '')) && Columns::fold((string) $student->first_names) === Columns::fold((string) ($data['first_names'] ?? ''));
        });
        $student = $matches->count() === 1 ? $matches->first() : null;
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
                    if ($old instanceof \DateTimeInterface) {
                        $old = $old->format('Y-m-d');
                    }
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
                    if ($record->level_id === $data['level_id'] && $record->program_id === $data['program_id']) {
                        $duplicate = true;
                    } else {
                        $warnings[] = $issue('record', 'record_conflict', 'Une déclaration de cette année existe avec un autre niveau ou parcours.');
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

    /** @param array<int,Level|Program> $items */
    private function resolve(array $items, mixed $value): Level|Program|null
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
