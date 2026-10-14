<?php

namespace App\Models;

use App\Enums\ScholarshipApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Readable business history of a file; ActivityLog remains the technical audit.
 *
 * @property ScholarshipApplicationStatus|null $from_status
 * @property ScholarshipApplicationStatus $to_status
 * @property Carbon $created_at
 */
#[Fillable(['scholarship_application_id', 'from_status', 'to_status', 'actor_id', 'reason'])]
class ScholarshipApplicationStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['from_status' => ScholarshipApplicationStatus::class, 'to_status' => ScholarshipApplicationStatus::class, 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User,$this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
