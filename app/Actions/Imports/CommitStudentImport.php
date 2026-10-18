<?php

namespace App\Actions\Imports;

use App\Enums\StudentImportRowStatus as RowStatus;
use App\Enums\StudentImportStatus as ImportStatus;
use App\Jobs\CommitStudentImportJob;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\User;
use App\Support\Imports\ImportProgress;
use App\Support\StudentIdentityLocks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommitStudentImport
{
    public function __construct(private PersistStudentImportRow $persist) {}

    /** Confirms now, or in the queue for a large file: the import is then marked « committing » and the page follows it. */
    public function run(User $actor, StudentImport $import): void
    {
        if ($import->total_rows <= (int) config('student_imports.queue_threshold')) {
            $this->handle($actor, $import);

            return;
        }
        Gate::forUser($actor)->authorize('commit', $import);
        DB::transaction(function () use ($actor, $import) {
            $locked = StudentImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($locked->status !== ImportStatus::Ready) {
                throw ValidationException::withMessages(['import' => 'Cet import doit être analysé avant confirmation.']);
            }
            $locked->update(['status' => ImportStatus::Committing, 'failure_reason' => null]);
            ImportProgress::put($locked, 'waiting', 0, $locked->valid_rows);
            CommitStudentImportJob::dispatch($actor, $locked)->afterCommit();
        });
    }

    public function handle(User $actor, StudentImport $import, bool $queued = false): void
    {
        Gate::forUser($actor)->authorize('commit', $import);
        try {
            DB::transaction(function () use ($actor, $import, $queued) {
                $import = StudentImport::query()->lockForUpdate()->findOrFail($import->id);
                // Retry after a successful commit is a no-op, including its audit.
                if ($import->status === ImportStatus::Completed) {
                    return;
                }
                if ($import->status !== ImportStatus::Ready && ! ($queued && $import->status === ImportStatus::Committing)) {
                    throw ValidationException::withMessages(['import' => 'Cet import doit être analysé avant confirmation.']);
                }
                // A global import checks the establishment of each row in the re-analysis below.
                if (($import->establishment_id !== null && ! Establishment::query()->sharedLock()->findOrFail($import->establishment_id)->is_active) || ! AcademicYear::query()->sharedLock()->findOrFail($import->academic_year_id)->is_active) {
                    throw ValidationException::withMessages(['import' => 'L’établissement ou l’année universitaire est désormais inactif.']);
                }
                $import->update(['status' => ImportStatus::Committing]);
                ImportProgress::put($import, 'prepare', 0, $import->valid_rows);
                $keys = [];
                $import->rows()->where('status', RowStatus::Valid)->chunkById((int) config('student_imports.chunk_size'), function ($rows) use (&$keys) {
                    foreach ($rows as $row) {
                        array_push($keys, ...StudentIdentityLocks::keys($row->normalized_data ?? []));
                    }
                });
                StudentIdentityLocks::acquire($keys);
                // Missing mentions/programs first, so that the re-analysis below resolves them.
                $references = app(CreateImportReferences::class)->handle($import);
                $analyzer = new AnalyzeStudentRow($import, $actor, true);
                $created = 0;
                $updated = 0;
                $declared = 0;
                $matched = 0;
                $done = 0;
                ImportProgress::put($import, 'rows', 0, $import->valid_rows);
                $import->rows()->where('status', RowStatus::Valid)->chunkById((int) config('student_imports.chunk_size'), function ($rows) use ($import, $actor, $analyzer, &$created, &$updated, &$declared, &$matched, &$done) {
                    $results = [];
                    $analyzer->loadCandidates($rows->map(fn ($row) => $row->normalized_data ?? [])->values()->all(), true);
                    foreach ($rows as $row) {
                        // Matching, conflicts and referentials are checked again after locks.
                        $written = $this->persist->handle($import, $actor, $analyzer->handle($row->normalized_data ?? []));
                        $results[$row->id] = $written['row'];
                        $created += (int) $written['created'];
                        $updated += (int) $written['updated'];
                        $declared += (int) ($written['outcome'] === 'imported');
                        $matched += (int) ($written['outcome'] === 'matched');
                    }
                    StudentImportRow::writeResults($results);
                    $done += $rows->count();
                    ImportProgress::put($import, 'rows', $done, $import->valid_rows);
                });
                $counts = $import->rows()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
                foreach (RowStatus::cases() as $status) {
                    $import->setAttribute($status->value.'_rows', (int) ($counts[$status->value] ?? 0));
                }
                $import->fill(['reference_changes' => [...$references, 'created' => true], 'status' => ImportStatus::Completed, 'created_rows' => $created, 'updated_rows' => $updated, 'declared_rows' => $declared, 'matched_rows' => $matched, 'skipped_rows' => $import->total_rows - $declared - $matched, 'committed_at' => now()])->save();
                $import->audit($actor, 'committed');
            }, 5);
        } catch (Throwable $error) {
            if ($error instanceof ValidationException) {
                throw $error;
            }
            // Preserve a failure marker after all student/record/row/audit writes roll back.
            StudentImport::query()->whereKey($import->id)->whereIn('status', [ImportStatus::Ready, ImportStatus::Committing])->update(['status' => ImportStatus::Failed, 'failed_at' => now(), 'failure_reason' => 'Échec transactionnel. Aucun changement étudiant n’a été conservé.']);
            throw ValidationException::withMessages(['import' => 'L’import a échoué et a été annulé intégralement. Contactez l’administrateur.']);
        } finally {
            ImportProgress::forget($import);
        }
    }
}
