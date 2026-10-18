<?php

namespace App\Models;

use App\Enums\StudentImportRowStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property StudentImportRowStatus $status
 * @property array<int,mixed> $raw_data
 * @property array<string,string>|null $corrections values corrected in the preview, by field
 * @property list<string>|null $identity_updates fields of the existing record to replace with the file value
 * @property array<string,mixed>|null $normalized_data
 * @property array<int,array<string,string>>|null $errors
 * @property array<int,array<string,string>>|null $warnings
 */
#[Fillable(['student_import_id', 'row_number', 'raw_data', 'corrections', 'identity_updates', 'normalized_data', 'status', 'errors', 'warnings', 'matched_student_id', 'matched_student_record_id', 'resolution'])]
class StudentImportRow extends Model
{
    protected function casts(): array
    {
        return ['status' => StudentImportRowStatus::class, 'raw_data' => 'array', 'corrections' => 'array', 'identity_updates' => 'array', 'normalized_data' => 'array', 'errors' => 'array', 'warnings' => 'array'];
    }

    /** Columns written back by the analysis and the confirmation. */
    private const RESULT_COLUMNS = ['normalized_data', 'status', 'errors', 'warnings', 'matched_student_id', 'matched_student_record_id', 'resolution'];

    /**
     * Writes the results of many rows in one statement (one per chunk instead of one per row:
     * three quarters of the time of a 12 000-row analysis went into single updates).
     *
     * @param  array<int,array<string,mixed>>  $results  row id => result (AnalyzeStudentRow) and resolution
     */
    public static function writeResults(array $results): void
    {
        if ($results === []) {
            return;
        }
        $sets = [];
        $bindings = [];
        foreach (self::RESULT_COLUMNS as $column) {
            $cases = [];
            foreach ($results as $id => $result) {
                $value = $result[$column] ?? null;
                $cases[] = 'WHEN ? THEN ?';
                $bindings[] = $id;
                $bindings[] = match (true) {
                    $value instanceof \BackedEnum => $value->value,
                    is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
                    default => $value,
                };
            }
            $sets[] = $column.' = CASE id '.implode(' ', $cases).' END';
        }
        $sets[] = 'updated_at = ?';
        $bindings[] = now();
        $ids = array_keys($results);
        static::query()->getConnection()->update('UPDATE student_import_rows SET '.implode(', ', $sets).' WHERE id IN ('.implode(', ', array_fill(0, count($ids), '?')).')', [...$bindings, ...$ids]);
    }

    /** @return BelongsTo<StudentImport,$this> */
    public function studentImport(): BelongsTo
    {
        return $this->belongsTo(StudentImport::class);
    }
}
