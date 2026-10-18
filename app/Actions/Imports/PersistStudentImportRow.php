<?php

namespace App\Actions\Imports;

use App\Enums\ImportKind;
use App\Enums\SituationVerification;
use App\Enums\StudentImportRowStatus as RowStatus;
use App\Enums\StudentRecordStatus;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\Scholarships\ScholarshipRequestReference;
use App\Support\StudentImportColumns;
use Illuminate\Support\Facades\Gate;

/**
 * Writes one analysed row (AnalyzeStudentRow result, after locks): identity created or completed,
 * then the situation of the year created, or the existing one confirmed. Shared by the confirmation
 * of the file and by the import of a single row corrected afterwards.
 */
class PersistStudentImportRow
{
    /**
     * @param  array<string,mixed>  $result
     * @return array{row: array<string,mixed>, outcome: 'skipped'|'imported'|'matched', created: bool, updated: bool}
     */
    public function handle(StudentImport $import, User $actor, array $result): array
    {
        $values = $result['normalized_data'];
        $unresolved = ($values['establishment_id'] ?? null) === null || filled($values['_new_establishment'] ?? null) || filled($values['_new_level'] ?? null) || filled($values['_new_mention'] ?? null) || filled($values['_new_program'] ?? null);
        if ($result['status'] !== RowStatus::Valid || $unresolved) {
            return ['row' => [...$result, 'resolution' => 'skipped'], 'outcome' => 'skipped', 'created' => false, 'updated' => false];
        }
        $data = array_intersect_key($values, array_flip(StudentImportColumns::IDENTITY_FIELDS));
        $student = $result['matched_student_id'] === null ? new Student : Student::query()->whereKey($result['matched_student_id'])->lockForUpdate()->firstOrFail();
        $created = ! $student->exists;
        if ($created) {
            $student->fill($data);
        } else {
            // Values chosen in the preview replace the record, when the actor may correct this identity.
            $replace = is_array($values['_identity_updates'] ?? null) && Gate::forUser($actor)->allows('update', $student) ? $values['_identity_updates'] : [];
            foreach ($data as $field => $value) {
                if (filled($value) && (blank($student->getAttribute($field)) || in_array($field, $replace, true))) {
                    $student->setAttribute($field, $value);
                }
            }
        }
        $updated = ! $created && $student->isDirty();
        $student->save();
        if (filled($values['_confirms_record'] ?? null)) {
            // The confirmed establishment list or PNES base proves the existing situation.
            // Activate only a pending situation: an administrative deactivation stays in place.
            $record = StudentRecord::query()->whereKey($values['_confirms_record'])->where('student_id', $student->id)
                ->where('academic_year_id', $import->academic_year_id)->where('establishment_id', $values['establishment_id'])->lockForUpdate()->firstOrFail();
            $record->update([
                'verification' => SituationVerification::Verified,
                'matched_import_id' => $import->id,
                ...($record->status === StudentRecordStatus::Pending ? ['status' => StudentRecordStatus::Active] : []),
                ...($import->kind === ImportKind::Pnes ? ['is_scholarship_holder' => true] : []),
            ]);
            $changed = array_values(array_intersect(['verification', 'status'], array_keys($record->getChanges())));
            if ($changed !== []) {
                activity('student_records')->causedBy($actor)->performedOn($record)->event('verified_by_import')
                    ->withProperties(['import_id' => $import->id, 'changed_fields' => $changed])->log('verified_by_import');
            }

            return ['row' => [...$result, 'matched_student_id' => $student->id, 'matched_student_record_id' => $record->id, 'resolution' => 'matched'], 'outcome' => 'matched', 'created' => $created, 'updated' => $updated];
        }
        // A request date without reference in the file: the platform gives the reference.
        $reference = $values['scholarship_request_reference'] ?? null;
        if (filled($values['scholarship_requested_at'] ?? null) && blank($reference)) {
            Establishment::query()->whereKey($values['establishment_id'])->lockForUpdate()->first();
            $reference = ScholarshipRequestReference::next((int) $values['establishment_id'], (int) $import->academic_year_id);
        }
        $record = StudentRecord::query()->create(['student_id' => $student->id, 'academic_year_id' => $import->academic_year_id, 'establishment_id' => $values['establishment_id'], 'level_id' => $values['level_id'], 'program_id' => $values['program_id'], 'repeat_code' => $values['repeat_code'], 'scholarship_requested_at' => $values['scholarship_requested_at'] ?? null, 'scholarship_request_reference' => $reference, 'status' => 'active',
            'source' => $import->kind->source(), 'verification' => SituationVerification::Verified, 'is_scholarship_holder' => $import->kind === ImportKind::Pnes,
            'preinscription_reference' => $values['preinscription_reference'] ?? null, 'declared_by' => $actor->id, 'declared_at' => now()]);

        return ['row' => [...$result, 'matched_student_id' => $student->id, 'matched_student_record_id' => $record->id, 'resolution' => 'imported'], 'outcome' => 'imported', 'created' => $created, 'updated' => $updated];
    }
}
