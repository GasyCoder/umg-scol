<?php

namespace App\Models;

use App\Enums\StudentLinkStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property StudentLinkStatus $status */
#[Fillable(['user_id', 'student_id', 'status', 'requested_at', 'reviewed_at', 'reviewed_by', 'rejection_reason'])]
class StudentLinkRequest extends Model
{
    protected function casts(): array
    {
        return ['status' => StudentLinkStatus::class, 'requested_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Student,$this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User,$this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<StudentLinkRequest>  $query
     * @return Builder<StudentLinkRequest>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when($user->hasRole('establishment'), fn ($q) => $q->whereHas('student', fn ($student) => $student->currentlyDeclaredBy($user->establishment_id)));
    }
}
