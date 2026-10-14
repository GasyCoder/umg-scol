<?php

namespace App\Models;

use App\Enums\ScholarshipCampaignStatus;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonInterface;
use Database\Factories\ScholarshipCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ScholarshipCampaignStatus $status
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 */
#[Fillable(['academic_year_id', 'name', 'starts_at', 'ends_at', 'status', 'notes', 'created_by'])]
class ScholarshipCampaign extends Model
{
    /** @use HasFactory<ScholarshipCampaignFactory> */
    use HasFactory, HasPublicUuid;

    protected $attributes = ['status' => 'draft', 'last_application_sequence' => 0];

    protected function casts(): array
    {
        return ['status' => ScholarshipCampaignStatus::class, 'starts_at' => 'date', 'ends_at' => 'date', 'academic_year_id' => 'integer', 'last_application_sequence' => 'integer'];
    }

    /** @return BelongsTo<AcademicYear,$this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<User,$this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ScholarshipApplication,$this> */
    public function applications(): HasMany
    {
        return $this->hasMany(ScholarshipApplication::class);
    }
}
