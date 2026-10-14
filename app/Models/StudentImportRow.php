<?php

namespace App\Models;

use App\Enums\StudentImportRowStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property StudentImportRowStatus $status
 * @property array<int,mixed> $raw_data
 * @property array<string,mixed>|null $normalized_data
 * @property array<int,array<string,string>>|null $errors
 * @property array<int,array<string,string>>|null $warnings
 */
#[Fillable(['student_import_id', 'row_number', 'raw_data', 'normalized_data', 'status', 'errors', 'warnings', 'matched_student_id', 'matched_student_record_id', 'resolution'])]
class StudentImportRow extends Model
{
    protected function casts(): array
    {
        return ['status' => StudentImportRowStatus::class, 'raw_data' => 'array', 'normalized_data' => 'array', 'errors' => 'array', 'warnings' => 'array'];
    }

    /** @return BelongsTo<StudentImport,$this> */
    public function studentImport(): BelongsTo
    {
        return $this->belongsTo(StudentImport::class);
    }
}
