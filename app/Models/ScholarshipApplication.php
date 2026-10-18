<?php

namespace App\Models;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\SituationVerification;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The paper scholarship file received and processed by the Scolarité Centrale.
 * Personal data stays in Student/StudentRecord and is never copied here.
 *
 * @property ScholarshipApplicationStatus $status
 * @property Carbon $received_at
 * @property Carbon|null $review_started_at
 * @property Carbon|null $decided_at
 */
#[Fillable(['application_number', 'academic_year_id', 'sequence', 'student_id', 'student_record_id', 'ddb_number', 'ddb_key', 'status', 'received_by', 'received_at', 'review_started_by', 'review_started_at', 'decided_by', 'decided_at', 'decision_reason', 'internal_notes'])]
#[Hidden(['ddb_key', 'live_marker'])]
class ScholarshipApplication extends Model
{
    use HasPublicUuid, Trashable;

    protected function casts(): array
    {
        return [
            'status' => ScholarshipApplicationStatus::class, 'received_at' => 'datetime', 'review_started_at' => 'datetime', 'decided_at' => 'datetime',
            'academic_year_id' => 'integer', 'student_id' => 'integer', 'student_record_id' => 'integer', 'sequence' => 'integer',
        ];
    }

    /** Non-destructive normalization for display: trimmed, inner whitespace collapsed. */
    public static function normalizeDdb(?string $value): ?string
    {
        $value = $value === null ? '' : trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }

    /** Comparison key for duplicate detection inside an academic year. */
    public static function ddbKey(?string $value): ?string
    {
        $value = self::normalizeDdb($value);

        return $value === null ? null : mb_strtoupper(str_replace(' ', '', $value));
    }

    /**
     * The one number shown for a paper file: its DDB number. The technical application number
     * (unique on the platform) stays internal and only stands in for an old file without DDB.
     */
    public function reference(): string
    {
        return $this->ddb_number ?? $this->application_number;
    }

    /** Shown status of a received file whose situation still waits for the establishment's list. */
    public const AWAITING_LIST = 'awaiting_list';

    public const AWAITING_LIST_LABEL = 'En attente de la liste';

    /**
     * Status shown to the agents: a received file whose situation is not yet confirmed by the
     * establishment's list is « En attente de la liste ». Derived, never stored: it turns into
     * « Reçu » by itself once the list is imported. Expects studentRecord loaded.
     *
     * @return array{value:string,label:string}
     */
    public function displayStatus(): array
    {
        return $this->status === ScholarshipApplicationStatus::Pending && $this->studentRecord?->verification !== SituationVerification::Verified
            ? ['value' => self::AWAITING_LIST, 'label' => self::AWAITING_LIST_LABEL]
            : ['value' => $this->status->value, 'label' => $this->status->label()];
    }

    /**
     * Files of a student for an academic year: one scholarship request per student and per year (ADR-082).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOfStudentYear(Builder $query, int $studentId, int $academicYearId): void
    {
        $query->where('student_id', $studentId)->where('academic_year_id', $academicYearId);
    }

    /** @return BelongsTo<AcademicYear,$this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<Student,$this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StudentRecord,$this> */
    public function studentRecord(): BelongsTo
    {
        return $this->belongsTo(StudentRecord::class);
    }

    /** @return BelongsTo<User,$this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User,$this> */
    public function reviewStarter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_started_by');
    }

    /** @return BelongsTo<User,$this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return HasMany<ScholarshipApplicationCheck,$this> */
    public function checks(): HasMany
    {
        return $this->hasMany(ScholarshipApplicationCheck::class);
    }

    /** @return HasMany<ScholarshipApplicationStatusHistory,$this> */
    public function history(): HasMany
    {
        return $this->hasMany(ScholarshipApplicationStatusHistory::class);
    }
}
