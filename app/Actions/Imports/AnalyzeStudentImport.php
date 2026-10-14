<?php

namespace App\Actions\Imports;

use App\Enums\StudentImportRowStatus as RowStatus;
use App\Enums\StudentImportStatus as ImportStatus;
use App\Models\StudentImport;
use App\Models\User;
use App\Support\StudentIdentityLocks;
use App\Support\StudentImportColumns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnalyzeStudentImport
{
    /** @param array<string,int> $mapping */
    public function handle(User $actor, StudentImport $import, array $mapping): void
    {
        Gate::forUser($actor)->authorize('analyze', $import);
        DB::transaction(function () use ($actor, $import, $mapping) {
            $import = StudentImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($import->status !== ImportStatus::Uploaded && $import->status !== ImportStatus::Ready) {
                throw ValidationException::withMessages(['mapping' => 'Cet import ne peut plus être analysé.']);
            }
            $import->update(['status' => ImportStatus::Analyzing, 'started_at' => now(), 'mapping' => $mapping]);
            $analyzer = new AnalyzeStudentRow($import, $actor);
            $seen = [];
            $counts = ['valid' => 0, 'warning' => 0, 'rejected' => 0, 'duplicate' => 0];
            $import->rows()->chunkById((int) config('student_imports.chunk_size'), function ($rows) use ($import, $mapping, $analyzer, &$seen, &$counts) {
                $data = $rows->map(fn ($row) => StudentImportColumns::normalize($row->raw_data, $mapping, $import->excel_calendar));
                $analyzer->loadCandidates($data->values()->all());
                foreach ($rows as $i => $row) {
                    $result = $analyzer->handle($data[$i]);
                    $keys = StudentIdentityLocks::keys($result['normalized_data']);
                    if ($result['status'] !== RowStatus::Rejected) {
                        if (array_intersect($keys, array_keys($seen)) !== []) {
                            $result['status'] = RowStatus::Duplicate;
                            $result['warnings'][] = ['field' => 'identity', 'code' => 'file_duplicate', 'message' => 'Une identité correspondante apparaît déjà dans ce fichier.'];
                        }
                        foreach ($keys as $key) {
                            $seen[$key] = true;
                        }
                    }
                    $counts[$result['status']->value]++;
                    $row->update([...$result, 'resolution' => null]);
                }
            });
            foreach ($counts as $status => $count) {
                $import->setAttribute($status.'_rows', $count);
            }
            $import->fill(['status' => ImportStatus::Ready, 'analyzed_at' => now()])->save();
            $import->audit($actor, 'analyzed');
        }, 5);
    }
}
