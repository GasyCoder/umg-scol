<?php

namespace App\Actions\Imports;

use App\Enums\StudentImportRowStatus as RowStatus;
use App\Enums\StudentImportStatus as ImportStatus;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentIdentityLocks;
use App\Support\StudentImportColumns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommitStudentImport
{
    public function handle(User $actor, StudentImport $import): void
    {
        Gate::forUser($actor)->authorize('commit', $import);
        try {
            DB::transaction(function () use ($actor, $import) {
                $import = StudentImport::query()->lockForUpdate()->findOrFail($import->id);
                // Retry after a successful commit is a no-op, including its audit.
                if ($import->status === ImportStatus::Completed) {
                    return;
                }
                if ($import->status !== ImportStatus::Ready) {
                    throw ValidationException::withMessages(['import' => 'Cet import doit être analysé avant confirmation.']);
                }
                if (! Establishment::query()->sharedLock()->findOrFail($import->establishment_id)->is_active || ! AcademicYear::query()->sharedLock()->findOrFail($import->academic_year_id)->is_active) {
                    throw ValidationException::withMessages(['import' => 'L’établissement ou l’année universitaire est désormais inactif.']);
                }
                $import->update(['status' => ImportStatus::Committing]);
                $keys = [];
                $import->rows()->where('status', RowStatus::Valid)->chunkById((int) config('student_imports.chunk_size'), function ($rows) use (&$keys) {
                    foreach ($rows as $row) {
                        array_push($keys, ...StudentIdentityLocks::keys($row->normalized_data ?? []));
                    }
                });
                StudentIdentityLocks::acquire($keys);
                $analyzer = new AnalyzeStudentRow($import, $actor, true);
                $created = 0;
                $updated = 0;
                $declared = 0;
                $import->rows()->where('status', RowStatus::Valid)->chunkById((int) config('student_imports.chunk_size'), function ($rows) use ($import, $actor, $analyzer, &$created, &$updated, &$declared) {
                    $analyzer->loadCandidates($rows->map(fn ($row) => $row->normalized_data ?? [])->values()->all(), true);
                    foreach ($rows as $row) {
                        // Matching, conflicts and referentials are checked again after locks.
                        $result = $analyzer->handle($row->normalized_data ?? []);
                        if ($result['status'] !== RowStatus::Valid) {
                            $row->update([...$result, 'resolution' => 'skipped']);

                            continue;
                        }
                        $values = $result['normalized_data'];
                        $data = array_intersect_key($values, array_flip(StudentImportColumns::IDENTITY_FIELDS));
                        $student = $result['matched_student_id'] === null ? new Student : Student::query()->whereKey($result['matched_student_id'])->lockForUpdate()->firstOrFail();
                        if (! $student->exists) {
                            $student->fill($data);
                            $created++;
                        } else {
                            foreach ($data as $field => $value) {
                                if (blank($student->getAttribute($field)) && filled($value)) {
                                    $student->setAttribute($field, $value);
                                }
                            }
                            if ($student->isDirty()) {
                                $updated++;
                            }
                        }
                        $student->save();
                        $record = StudentRecord::query()->create(['student_id' => $student->id, 'academic_year_id' => $import->academic_year_id, 'establishment_id' => $import->establishment_id, 'level_id' => $values['level_id'], 'program_id' => $values['program_id'], 'repeat_code' => $values['repeat_code'], 'scholarship_requested_at' => $values['scholarship_requested_at'] ?? null, 'scholarship_request_reference' => $values['scholarship_request_reference'] ?? null, 'status' => 'active', 'declared_by' => $actor->id, 'declared_at' => now()]);
                        $row->update([...$result, 'matched_student_id' => $student->id, 'matched_student_record_id' => $record->id, 'resolution' => 'imported']);
                        $declared++;
                    }
                });
                $counts = $import->rows()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
                foreach (RowStatus::cases() as $status) {
                    $import->setAttribute($status->value.'_rows', (int) ($counts[$status->value] ?? 0));
                }
                $import->fill(['status' => ImportStatus::Completed, 'created_rows' => $created, 'updated_rows' => $updated, 'declared_rows' => $declared, 'skipped_rows' => $import->total_rows - $declared, 'committed_at' => now()])->save();
                $import->audit($actor, 'committed');
            }, 5);
        } catch (Throwable $error) {
            if ($error instanceof ValidationException) {
                throw $error;
            }
            // Preserve a failure marker after all student/record/row/audit writes roll back.
            StudentImport::query()->whereKey($import->id)->where('status', ImportStatus::Ready)->update(['status' => ImportStatus::Failed, 'failed_at' => now(), 'failure_reason' => 'Échec transactionnel. Aucun changement étudiant n’a été conservé.']);
            throw ValidationException::withMessages(['import' => 'L’import a échoué et a été annulé intégralement. Contactez l’administrateur.']);
        }
    }
}
