<?php

namespace App\Models;

use App\Enums\StudentImportStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $excel_calendar
 * @property int $total_rows
 * @property int $rejected_rows
 * @property int $declared_rows
 * @property StudentImportStatus $status
 * @property array<int,string> $headers
 * @property array<string,int> $mapping
 */
#[Fillable(['establishment_id', 'academic_year_id', 'user_id', 'original_filename', 'stored_path', 'disk', 'status', 'headers', 'mapping', 'excel_calendar', 'total_rows', 'valid_rows', 'warning_rows', 'rejected_rows', 'duplicate_rows', 'created_rows', 'updated_rows', 'declared_rows', 'skipped_rows', 'started_at', 'analyzed_at', 'committed_at', 'failed_at', 'failure_reason', 'purged_at'])]
class StudentImport extends Model
{
    use HasPublicUuid, Trashable;

    protected function casts(): array
    {
        return ['status' => StudentImportStatus::class, 'headers' => 'array', 'mapping' => 'array', 'excel_calendar' => 'integer', 'started_at' => 'datetime', 'analyzed_at' => 'datetime', 'committed_at' => 'datetime', 'failed_at' => 'datetime', 'purged_at' => 'datetime'];
    }

    /** @return HasMany<StudentImportRow,$this> */
    public function rows(): HasMany
    {
        return $this->hasMany(StudentImportRow::class);
    }

    /** @return BelongsTo<Establishment,$this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return BelongsTo<AcademicYear,$this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<StudentImport>  $query
     * @return Builder<StudentImport>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when($user->hasRole('establishment'), fn ($q) => $q->where('establishment_id', $user->establishment_id));
    }

    public function audit(User $actor, string $event): void
    {
        activity('student_imports')->causedBy($actor)->performedOn($this)->event($event)->withProperties($this->only(['establishment_id', 'academic_year_id', 'total_rows', 'valid_rows', 'warning_rows', 'rejected_rows', 'duplicate_rows', 'created_rows', 'updated_rows', 'declared_rows', 'skipped_rows']))->log($event);
    }
}
