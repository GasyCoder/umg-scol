<?php

namespace App\Actions\Imports;

use App\Enums\StudentImportRowStatus as RowStatus;
use App\Enums\StudentImportStatus;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\User;
use App\Support\StudentIdentityLocks;
use App\Support\StudentImportColumns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Corrects a row of an import: values changed in the preview and fields of the existing record to
 * replace with the file value. The uploaded file itself is never changed.
 * - Before confirmation: the whole file is analysed again (duplicates inside the file, references).
 * - After confirmation: the row, not imported, is checked again against the platform and imported alone.
 */
class CorrectStudentImportRow
{
    public function __construct(private AnalyzeStudentImport $analyze, private PersistStudentImportRow $persist) {}

    /**
     * @param  array<string,string|null>  $values  field => corrected value (empty = cell emptied)
     * @param  list<string>|null  $identityUpdates  null = unchanged
     * @return 'reanalysed'|'imported'|'matched'|'skipped'
     */
    public function handle(User $actor, StudentImport $import, StudentImportRow $row, array $values, ?array $identityUpdates = null): string
    {
        if ($import->status === StudentImportStatus::Completed) {
            return $this->importAlone($actor, $import, $row, $values, $identityUpdates);
        }
        Gate::forUser($actor)->authorize('analyze', $import);
        DB::transaction(function () use ($actor, $import, $row, $values, $identityUpdates) {
            $import = StudentImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($import->status !== StudentImportStatus::Ready) {
                throw ValidationException::withMessages(['values' => 'Cet import ne peut plus être corrigé.']);
            }
            $this->remember($actor, $import, $import->rows()->whereKey($row->id)->lockForUpdate()->firstOrFail(), $values, $identityUpdates);
        });
        $this->analyze->run($actor, $import->refresh(), $import->mapping);

        return 'reanalysed';
    }

    /**
     * @param  array<string,string|null>  $values
     * @param  list<string>|null  $identityUpdates
     * @return 'imported'|'matched'|'skipped'
     */
    private function importAlone(User $actor, StudentImport $import, StudentImportRow $row, array $values, ?array $identityUpdates): string
    {
        Gate::forUser($actor)->authorize('commit', $import);

        return DB::transaction(function () use ($actor, $import, $row, $values, $identityUpdates) {
            $import = StudentImport::query()->lockForUpdate()->findOrFail($import->id);
            $row = $import->rows()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            if (in_array($row->resolution, ['imported', 'matched'], true)) {
                throw ValidationException::withMessages(['values' => 'Cette ligne est déjà importée.']);
            }
            $this->remember($actor, $import, $row, $values, $identityUpdates);
            $data = StudentImportColumns::preview($row, $import->mapping ?? [], $import->excel_calendar);
            StudentIdentityLocks::acquire(StudentIdentityLocks::keys($data));
            $result = $this->analyse($import, $actor, $data);
            if ($result['status'] === RowStatus::Valid && $this->needsReferences($result['normalized_data'])) {
                // Mention, program, level or establishment still missing: created for this row, then checked again.
                StudentImportRow::writeResults([$row->id => [...$result, 'resolution' => null]]);
                app(CreateImportReferences::class)->handle($import);
                $result = $this->analyse($import, $actor, $data);
            }
            $written = $this->persist->handle($import, $actor, $result);
            StudentImportRow::writeResults([$row->id => $written['row']]);
            if ($written['outcome'] === 'skipped') {
                return 'skipped';
            }
            $counts = $import->rows()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
            foreach (RowStatus::cases() as $status) {
                $import->setAttribute($status->value.'_rows', (int) ($counts[$status->value] ?? 0));
            }
            $declared = $import->declared_rows + (int) ($written['outcome'] === 'imported');
            $matched = (int) $import->getAttribute('matched_rows') + (int) ($written['outcome'] === 'matched');
            $import->fill(['created_rows' => (int) $import->getAttribute('created_rows') + (int) $written['created'], 'updated_rows' => (int) $import->getAttribute('updated_rows') + (int) $written['updated'],
                'declared_rows' => $declared, 'matched_rows' => $matched, 'skipped_rows' => $import->total_rows - $declared - $matched])->save();
            activity('student_imports')->causedBy($actor)->performedOn($import)->event('row_imported')
                ->withProperties(['row_number' => $row->row_number, 'outcome' => $written['outcome']])->log('row_imported');

            return $written['outcome'];
        });
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function analyse(StudentImport $import, User $actor, array $data): array
    {
        $analyzer = new AnalyzeStudentRow($import, $actor, true);
        $analyzer->loadCandidates([$data], true);

        return $analyzer->handle($data);
    }

    /** @param array<string,mixed> $values */
    private function needsReferences(array $values): bool
    {
        return filled($values['_new_establishment'] ?? null) || filled($values['_new_level'] ?? null) || filled($values['_new_mention'] ?? null) || filled($values['_new_program'] ?? null);
    }

    /**
     * Keeps the corrections and the identity choices on the row, and audits their field names only.
     *
     * @param  array<string,string|null>  $values
     * @param  list<string>|null  $identityUpdates
     */
    private function remember(User $actor, StudentImport $import, StudentImportRow $row, array $values, ?array $identityUpdates): void
    {
        $corrections = $row->corrections ?? [];
        $changed = [];
        foreach ($values as $field => $value) {
            $value = trim((string) $value);
            if (($corrections[$field] ?? null) !== $value) {
                $corrections[$field] = $value;
                $changed[] = $field;
            }
        }
        $updates = $identityUpdates === null ? ($row->identity_updates ?? []) : array_values(array_unique($identityUpdates));
        if ($changed === [] && $updates === ($row->identity_updates ?? [])) {
            return;
        }
        $row->update(['corrections' => $corrections, 'identity_updates' => $updates === [] ? null : $updates]);
        // Field names only: the values are personal data.
        activity('student_imports')->causedBy($actor)->performedOn($import)->event('row_corrected')
            ->withProperties(array_filter(['row_number' => $row->row_number, 'fields' => $changed, 'identity_updates' => $updates], fn ($value) => $value !== []))->log('row_corrected');
    }
}
