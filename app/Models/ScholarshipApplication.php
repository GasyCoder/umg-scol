<?php

namespace App\Models;

use App\Enums\ScholarshipApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
#[Fillable(['application_number', 'scholarship_campaign_id', 'sequence', 'student_id', 'student_record_id', 'ddb_number', 'ddb_key', 'status', 'received_by', 'received_at', 'review_started_by', 'review_started_at', 'decided_by', 'decided_at', 'decision_reason', 'internal_notes'])]
#[Hidden(['ddb_key'])]
class ScholarshipApplication extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ScholarshipApplicationStatus::class, 'received_at' => 'datetime', 'review_started_at' => 'datetime', 'decided_at' => 'datetime',
            'scholarship_campaign_id' => 'integer', 'student_id' => 'integer', 'student_record_id' => 'integer', 'sequence' => 'integer',
        ];
    }

    /** Non-destructive normalization for display: trimmed, inner whitespace collapsed. */
    public static function normalizeDdb(?string $value): ?string
    {
        $value = $value === null ? '' : trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }

    /** Comparison key for duplicate detection inside a campaign. */
    public static function ddbKey(?string $value): ?string
    {
        $value = self::normalizeDdb($value);

        return $value === null ? null : mb_strtoupper(str_replace(' ', '', $value));
    }

    /** @return BelongsTo<ScholarshipCampaign,$this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ScholarshipCampaign::class, 'scholarship_campaign_id');
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
