<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\InstitutionalReference;
use App\Models\Concerns\Trashable;
use Carbon\CarbonInterface;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 * @property int $last_application_sequence
 * @property int|null $scholarship_max_bac_year
 */
#[Fillable(['name', 'starts_at', 'ends_at', 'scholarship_max_bac_year', 'is_current', 'is_active'])]
#[Hidden(['current_marker'])]
class AcademicYear extends Model
{
    protected $attributes = ['is_current' => false, 'is_active' => true];

    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory, HasPublicUuid, InstitutionalReference, Trashable;

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'scholarship_max_bac_year' => 'integer', 'is_current' => 'boolean', 'is_active' => 'boolean', 'last_application_sequence' => 'integer'];
    }

    protected function referenceAuditAttributes(): array
    {
        return ['name', 'starts_at', 'ends_at', 'scholarship_max_bac_year', 'is_current', 'is_active'];
    }

    /**
     * The academic year whose paper scholarship files are received: the current and active one
     * (ADR-085). Null when no year is current: the reception is closed.
     */
    public static function receiving(): ?self
    {
        return self::query()->where('is_current', true)->where('is_active', true)->first();
    }

    /** @return HasMany<ScholarshipApplication, $this> */
    public function scholarshipApplications(): HasMany
    {
        return $this->hasMany(ScholarshipApplication::class);
    }

    /** @return HasMany<StudentRecord, $this> */
    public function studentRecords(): HasMany
    {
        return $this->hasMany(StudentRecord::class);
    }
}
