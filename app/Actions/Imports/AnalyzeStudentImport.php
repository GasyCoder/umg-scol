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
            // Key => identity of the first row that used it: same identity again is a duplicate,
            // another identity reusing a unique value (CIN, phone, email, request reference) is an error.
            $seen = [];
            $counts = ['valid' => 0, 'warning' => 0, 'rejected' => 0, 'duplicate' => 0];
            $import->rows()->chunkById((int) config('student_imports.chunk_size'), function ($rows) use ($import, $mapping, $analyzer, &$seen, &$counts) {
                $data = $rows->map(fn ($row) => StudentImportColumns::normalize($row->raw_data, $mapping, $import->excel_calendar));
                $analyzer->loadCandidates($data->values()->all());
                foreach ($rows as $i => $row) {
                    $result = $analyzer->handle($data[$i]);
                    $values = $result['normalized_data'];
                    $identity = StudentImportColumns::fold((string) ($values['last_name'] ?? '')).'|'.StudentImportColumns::fold((string) ($values['first_names'] ?? '')).'|'.($values['birth_date'] ?? '');
                    $keys = StudentIdentityLocks::uniqueValues($values);
                    if (filled($values['scholarship_request_reference'] ?? null)) {
                        $keys['scholarship_request_reference'] = StudentImportColumns::fold((string) $values['scholarship_request_reference']);
                    }
                    if ($result['status'] !== RowStatus::Rejected) {
                        $conflicts = array_keys(array_filter($keys, fn ($value, $field) => ($seen[$field.':'.StudentImportColumns::fold((string) $value)] ?? $identity) !== $identity, ARRAY_FILTER_USE_BOTH));
                        foreach ($conflicts as $field) {
                            $result['status'] = RowStatus::Rejected;
                            $result['errors'][] = ['field' => $field, 'code' => 'file_unique', 'message' => 'Valeur déjà utilisée par une autre ligne du fichier ('.StudentImportColumns::labels()[$field].').'];
                        }
                        if ($conflicts === [] && isset($seen['identity:'.$identity])) {
                            $result['status'] = RowStatus::Duplicate;
                            $result['warnings'][] = ['field' => 'identity', 'code' => 'file_duplicate', 'message' => 'Une identité correspondante apparaît déjà dans ce fichier.'];
                        }
                        foreach ([...$keys, 'identity' => $identity] as $field => $value) {
                            $seen[$field.':'.StudentImportColumns::fold((string) $value)] ??= $identity;
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
