<?php

namespace App\Models;

use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property StudentCorrectionField $field
 * @property StudentCorrectionStatus $status
 * @property string $proposed_value
 * @property Carbon $created_at
 * @property Carbon|null $reviewed_at
 */
#[Fillable(['student_id', 'user_id', 'field', 'proposed_value', 'current_fingerprint', 'status', 'student_message', 'rejection_reason', 'reviewed_by', 'reviewed_at'])]
#[Hidden(['current_fingerprint', 'pending_marker'])]
class StudentCorrectionRequest extends Model
{
    protected function casts(): array
    {
        return ['field' => StudentCorrectionField::class, 'status' => StudentCorrectionStatus::class, 'proposed_value' => 'encrypted', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Student,$this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User,$this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<StudentCorrectionRequest>  $query
     * @return Builder<StudentCorrectionRequest>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when($user->hasRole('establishment'), fn ($q) => $q->whereHas('student', fn ($student) => $student->currentlyDeclaredBy($user->establishment_id)));
    }
}
