<?php

namespace App\Actions\References;

use App\Models\Mention;
use App\Models\Program;
use App\Models\User;
use App\Support\References\ReferenceImportAnalyzer;
use App\Support\References\ReferenceTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Confirms a previewed import in one transaction: the rows are analyzed again under lock and written
 * through Eloquent (so the "references" activity log records each change), error and unchanged rows are skipped.
 */
class ImportReferences
{
    public function __construct(private ReferenceImportAnalyzer $analyzer) {}

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $input
     * @return array{created: int, updated: int, skipped: int}|null null when the referential changed since the preview
     */
    public function handle(User $actor, string $kind, array $input, string $fingerprint): ?array
    {
        $model = ReferenceTransfer::model($kind);
        Gate::forUser($actor)->authorize('create', $model);

        return DB::transaction(function () use ($actor, $kind, $input, $fingerprint, $model): ?array {
            $codes = array_unique(array_map(fn (array $row): string => mb_strtoupper(trim($row['values']['establishment'] ?? '')), $input));
            ReferenceTransfer::establishments($actor)->whereIn('code', $codes)->lockForUpdate()->get(['id']);
            $analysis = $this->analyzer->analyze($actor, $kind, $input);
            if (ReferenceImportAnalyzer::fingerprint($analysis) !== $fingerprint) {
                return null;
            }
            $created = $updated = 0;
            foreach ($analysis['rows'] as $row) {
                $attributes = ['name' => $row['name'], ...($kind === 'programs' ? ['mention_id' => $row['mention_id']] : [])];
                if ($row['status'] === 'create') {
                    $model::query()->create(['establishment_id' => $row['establishment_id'], 'code' => $row['code'] !== '' ? $row['code'] : null, ...$attributes]);
                    $created++;
                } elseif ($row['status'] === 'update') {
                    /** @var Mention|Program $record */
                    $record = $model::query()->whereKey($row['record_id'])->lockForUpdate()->firstOrFail();
                    Gate::forUser($actor)->authorize('update', $record);
                    $record->update($attributes);
                    $updated++;
                }
            }
            $skipped = $analysis['counts']['total'] - $created - $updated;
            activity('references')->causedBy($actor)->event('references_imported')
                ->withProperties(['kind' => $kind, 'total_rows' => $analysis['counts']['total'], 'created_rows' => $created, 'updated_rows' => $updated, 'skipped_rows' => $skipped])
                ->log('references_imported');

            return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
        });
    }
}
