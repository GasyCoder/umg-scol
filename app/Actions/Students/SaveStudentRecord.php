<?php

namespace App\Actions\Students;

use App\Enums\StudentRecordStatus;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveStudentRecord
{
    /** @param array<string,mixed> $data */
    public function handle(User $actor, Student $student, ?StudentRecord $record, array $data): StudentRecord
    {
        return $this->persist($actor, $student, $record, $data, false);
    }

    /** @param array<string,mixed> $data */
    public function initial(User $actor, Student $student, array $data): StudentRecord
    {
        return $this->persist($actor, $student, null, $data, true);
    }

    /** @param array<string,mixed> $data */
    private function persist(User $actor, Student $student, ?StudentRecord $record, array $data, bool $initial): StudentRecord
    {
        return DB::transaction(function () use ($actor, $student, $record, $data, $initial): StudentRecord {
            $lockedStudent = Student::query()->lockForUpdate()->findOrFail($student->id);
            $target = $record === null ? new StudentRecord : StudentRecord::query()->lockForUpdate()->findOrFail($record->id);
            if ($initial) {
                Gate::forUser($actor)->authorize('create', Student::class);
                abort_if($lockedStudent->studentRecords()->exists(), 403);
            } else {
                Gate::forUser($actor)->authorize($record === null ? 'create' : 'update', $record === null ? [StudentRecord::class, $lockedStudent] : $target);
            }
            if ($record !== null && ($target->student_id !== $student->id || (isset($data['academic_year_id']) && (int) $data['academic_year_id'] !== $target->academic_year_id) || (isset($data['establishment_id']) && (int) $data['establishment_id'] !== $target->establishment_id))) {
                throw ValidationException::withMessages(['academic_year_id' => 'L’année et l’établissement d’une déclaration existante ne peuvent pas être remplacés. Créez une autre déclaration.']);
            }
            if ($actor->hasRole('establishment')) {
                if (isset($data['establishment_id']) && (int) $data['establishment_id'] !== $actor->establishment_id) {
                    abort(403);
                }
                $data['establishment_id'] = $actor->establishment_id;
            }
            $target->fill($data);
            $target->student_id = $lockedStudent->id;
            if ($record === null) {
                $target->declaredBy()->associate($actor);
                $target->declared_at = now();
            }
            $isActivating = $target->status === StudentRecordStatus::Active && ($record === null || $target->isDirty('status'));
            if ($isActivating && ! $lockedStudent->is_active) {
                throw ValidationException::withMessages(['status' => 'Réactivez l’identité avant d’activer une déclaration.']);
            }
            foreach (['academic_year_id' => AcademicYear::class, 'establishment_id' => Establishment::class, 'level_id' => Level::class, 'program_id' => Program::class] as $field => $model) {
                $id = $target->getAttribute($field);
                if ($id === null && $field === 'program_id') {
                    continue;
                }
                $reference = $model::query()->lockForUpdate()->whereKey($id)->first();
                if ($reference === null || (($record === null || $target->isDirty($field) || $isActivating) && ! $reference->is_active)) {
                    throw ValidationException::withMessages([$field => 'Le référentiel doit exister et être actif pour cette déclaration.']);
                }
                if ($reference instanceof Program && $reference->establishment_id !== $target->establishment_id) {
                    throw ValidationException::withMessages(['program_id' => 'Le parcours doit appartenir à l’établissement déclaré.']);
                }
            }
            if (filled($target->scholarship_request_reference) && StudentRecord::query()->where('academic_year_id', $target->academic_year_id)->where('establishment_id', $target->establishment_id)
                ->where('scholarship_request_reference', $target->scholarship_request_reference)->when($target->exists, fn ($q) => $q->where('id', '!=', $target->id))->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['scholarship_request_reference' => 'Cette référence de demande de bourse est déjà attribuée dans l’établissement pour cette année.']);
            }
            $others = StudentRecord::query()->where('student_id', $student->id)->where('academic_year_id', $target->academic_year_id)->when($target->exists, fn ($q) => $q->where('id', '!=', $target->id));
            if ((clone $others)->where('establishment_id', $target->establishment_id)->exists() || ($target->status === StudentRecordStatus::Active && (clone $others)->where('status', 'active')->exists())) {
                throw ValidationException::withMessages(['academic_year_id' => 'Une déclaration existe déjà pour cette année. Aucun remplacement automatique n’est autorisé.']);
            }
            $target->save();

            return $target;
        }, 5);
    }
}
