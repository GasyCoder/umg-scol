<?php

namespace App\Actions\Imports;

use App\Enums\ImportKind;
use App\Enums\StudentImportRowStatus as RowStatus;
use App\Enums\StudentImportStatus as ImportStatus;
use App\Jobs\AnalyzeStudentImportJob;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\User;
use App\Support\Imports\ImportProgress;
use App\Support\StudentIdentityLocks;
use App\Support\StudentImportColumns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnalyzeStudentImport
{
    /**
     * Analyzes now, or in the queue for a large file: the import is then marked « analyzing » and the page follows it.
     *
     * @param  array<string,int>  $mapping
     */
    public function run(User $actor, StudentImport $import, array $mapping): void
    {
        if ($import->total_rows <= (int) config('student_imports.queue_threshold')) {
            $this->handle($actor, $import, $mapping);

            return;
        }
        Gate::forUser($actor)->authorize('analyze', $import);
        DB::transaction(function () use ($actor, $import, $mapping) {
            $locked = StudentImport::query()->lockForUpdate()->findOrFail($import->id);
            $this->ensureAnalyzable($locked);
            $locked->update(['status' => ImportStatus::Analyzing, 'started_at' => now(), 'mapping' => $mapping, 'failure_reason' => null]);
            ImportProgress::put($locked, 'waiting', 0, $locked->total_rows);
            AnalyzeStudentImportJob::dispatch($actor, $locked, $mapping)->afterCommit();
        });
    }

    /** @param array<string,int> $mapping */
    public function handle(User $actor, StudentImport $import, array $mapping, bool $queued = false): void
    {
        Gate::forUser($actor)->authorize('analyze', $import);
        try {
            DB::transaction(function () use ($actor, $import, $mapping, $queued) {
                $import = StudentImport::query()->lockForUpdate()->findOrFail($import->id);
                if (! ($queued && $import->status === ImportStatus::Analyzing)) {
                    $this->ensureAnalyzable($import);
                }
                $import->update(['status' => ImportStatus::Analyzing, 'started_at' => now(), 'mapping' => $mapping, 'failure_reason' => null]);
                $analyzer = new AnalyzeStudentRow($import, $actor);
                // Key => identity of the first row that used it: same identity again is a duplicate,
                // another identity reusing a unique value (CIN, phone, email, request reference) is an error.
                $seen = [];
                $counts = ['valid' => 0, 'warning' => 0, 'rejected' => 0, 'duplicate' => 0];
                $causes = [];
                $done = 0;
                ImportProgress::put($import, 'rows', 0, $import->total_rows);
                $import->rows()->chunkById((int) config('student_imports.chunk_size'), function ($rows) use ($import, $mapping, $analyzer, &$seen, &$counts, &$causes, &$done) {
                    $results = [];
                    $data = $rows->map(fn ($row) => StudentImportColumns::preview($row, $mapping, $import->excel_calendar));
                    $analyzer->loadCandidates($data->values()->all());
                    foreach ($rows as $i => $row) {
                        $result = $analyzer->handle($data[$i]);
                        $values = $result['normalized_data'];
                        $identity = StudentImportColumns::fold((string) ($values['last_name'] ?? '')).'|'.StudentImportColumns::fold((string) ($values['first_names'] ?? '')).'|'.($values['birth_date'] ?? '');
                        $keys = StudentIdentityLocks::uniqueValues($values);
                        if (filled($values['scholarship_request_reference'] ?? null)) {
                            // A request reference is unique per establishment.
                            $keys['scholarship_request_reference'] = ($values['establishment_id'] ?? '').'|'.StudentImportColumns::fold((string) $values['scholarship_request_reference']);
                        }
                        if ($result['status'] !== RowStatus::Rejected) {
                            $conflicts = array_keys(array_filter($keys, fn ($value, $field) => ($seen[$field.':'.StudentImportColumns::fold((string) $value)] ?? $identity) !== $identity, ARRAY_FILTER_USE_BOTH));
                            if ($import->kind === ImportKind::Pnes) {
                                // A phone or email shared in the PNES base is set aside on the later row, not a rejection.
                                foreach (array_intersect($conflicts, AnalyzeStudentRow::PNES_LENIENT) as $field) {
                                    $result['normalized_data'][$field] = null;
                                    $result['normalized_data']['_dropped'] = [...($result['normalized_data']['_dropped'] ?? []), StudentImportColumns::labels(ImportKind::Pnes)[$field].' : déjà sur une autre ligne du fichier'];
                                    unset($keys[$field]);
                                }
                                $conflicts = array_values(array_diff($conflicts, AnalyzeStudentRow::PNES_LENIENT));
                            }
                            foreach ($conflicts as $field) {
                                $result['status'] = RowStatus::Rejected;
                                $result['errors'][] = ['field' => $field, 'code' => 'file_unique', 'message' => 'Valeur déjà utilisée par une autre ligne du fichier ('.(StudentImportColumns::labels($import->kind)[$field] ?? $field).').'];
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
                        $this->collectCauses($causes, $result);
                        $results[$row->id] = [...$result, 'resolution' => null];
                    }
                    StudentImportRow::writeResults($results);
                    $done += $rows->count();
                    ImportProgress::put($import, 'rows', $done, $import->total_rows);
                });
                foreach ($counts as $status => $count) {
                    $import->setAttribute($status.'_rows', $count);
                }
                $import->setAttribute('reference_changes', [...$this->plannedReferences($import), 'created' => false]);
                usort($causes, fn (array $a, array $b) => $b['rows'] <=> $a['rows']);
                $import->setAttribute('issue_summary', array_slice($causes, 0, 8));
                $import->fill(['status' => ImportStatus::Ready, 'analyzed_at' => now()])->save();
                $import->audit($actor, 'analyzed');
            }, 5);
        } finally {
            ImportProgress::forget($import);
        }
    }

    private function ensureAnalyzable(StudentImport $import): void
    {
        if ($import->status !== ImportStatus::Uploaded && $import->status !== ImportStatus::Ready) {
            throw ValidationException::withMessages(['mapping' => 'Cet import ne peut plus être analysé.']);
        }
    }

    /**
     * Mentions, programs and domains the valid rows will create at confirmation, shown in the preview.
     *
     * @return array{establishments: list<string>, levels: list<string>, domains: list<string>, mentions: list<string>, programs: list<string>}
     */
    private function plannedReferences(StudentImport $import): array
    {
        $planned = ['establishments' => [], 'levels' => [], 'domains' => [], 'mentions' => [], 'programs' => []];
        $import->rows()->where('status', RowStatus::Valid)->chunkById((int) config('student_imports.chunk_size'), function ($rows) use ($import, &$planned) {
            foreach ($rows as $row) {
                $data = $row->normalized_data ?? [];
                // A global import names the establishment of each created mention/program.
                $where = $import->establishment_id === null && filled($data['_establishment_code'] ?? null) ? $data['_establishment_code'].' › ' : '';
                $new = is_array($data['_new_establishment'] ?? null) ? $data['_new_establishment'] : null;
                if ($new !== null) {
                    $planned['establishments'][(string) $new['code']] ??= $new['code'].' — '.$new['name'];
                }
                $scope = $new === null ? (string) ($data['establishment_id'] ?? '') : 'new:'.$new['code'];
                if (filled($data['_new_level'] ?? null)) {
                    $planned['levels'][(string) $data['_new_level']] ??= (string) $data['_new_level'];
                }
                if (is_array($data['_new_domain'] ?? null)) {
                    $planned['domains'][StudentImportColumns::fold((string) $data['_new_domain']['code'])] ??= '('.$data['_new_domain']['code'].') '.$data['_new_domain']['name'];
                }
                if (filled($data['_new_mention'] ?? null)) {
                    $planned['mentions'][$scope.'|'.StudentImportColumns::fold((string) $data['_new_mention'])] ??= $where.$data['_new_mention'];
                }
                if (filled($data['_new_program'] ?? null)) {
                    $mention = filled($data['mention'] ?? null) ? trim((string) $data['mention']) : null;
                    $planned['programs'][$scope.'|'.StudentImportColumns::fold((string) $mention).'|'.StudentImportColumns::fold((string) $data['_new_program'])] ??= $where.($mention === null ? '' : $mention.' › ').$data['_new_program'];
                }
            }
        });

        return array_map(fn (array $values) => array_values($values), $planned);
    }

    /** File values shown in the summary: referentials only, never personal data. */
    private const SUMMARY_VALUE_FIELDS = ['establishment', 'establishment_code', 'level', 'mention', 'program', 'sector', 'domain', 'training_type', 'repeat_code', 'nationality', 'bac_series', 'sex'];

    /**
     * Counts each blocking cause once per row (errors of rejected rows, warnings of warning rows).
     *
     * @param  array<string,array{field:string,code:string,message:string,severity:string,rows:int,values:list<string>}>  $causes
     * @param  array<string,mixed>  $result
     */
    private function collectCauses(array &$causes, array $result): void
    {
        $issues = match ($result['status']) {
            RowStatus::Rejected => [['error', $result['errors']]],
            RowStatus::Warning => [['warning', $result['warnings']]],
            default => [],
        };
        $counted = [];
        foreach ($issues as [$severity, $list]) {
            foreach ($list as $issue) {
                // Issues are built by AnalyzeStudentRow: field, code and message are always strings.
                $field = (string) $issue['field'];
                $key = $severity.'|'.$field.'|'.$issue['code'];
                if (isset($counted[$key])) {
                    continue;
                }
                $counted[$key] = true;
                $causes[$key] ??= ['field' => $field, 'code' => (string) $issue['code'], 'message' => (string) $issue['message'], 'severity' => $severity, 'rows' => 0, 'values' => []];
                $causes[$key]['rows']++;
                $value = $result['normalized_data'][$field] ?? null;
                if (in_array($field, self::SUMMARY_VALUE_FIELDS, true) && is_scalar($value) && filled($value) && count($causes[$key]['values']) < 5 && ! in_array((string) $value, $causes[$key]['values'], true)) {
                    $causes[$key]['values'][] = (string) $value;
                }
            }
        }
    }
}
