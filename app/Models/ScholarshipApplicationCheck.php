<?php

namespace App\Models;

use App\Enums\ScholarshipCheckStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ScholarshipCheckStatus $status
 * @property Carbon|null $checked_at
 */
#[Fillable(['scholarship_application_id', 'key', 'status', 'note', 'checked_by', 'checked_at'])]
class ScholarshipApplicationCheck extends Model
{
    protected function casts(): array
    {
        return ['status' => ScholarshipCheckStatus::class, 'checked_at' => 'datetime'];
    }

    public function label(): string
    {
        return (string) (config('scholarships.checks.'.$this->key) ?? $this->key);
    }

    /** @return BelongsTo<User,$this> */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
