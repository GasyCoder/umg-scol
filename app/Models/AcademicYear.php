<?php

namespace App\Models;

use App\Models\Concerns\InstitutionalReference;
use Carbon\CarbonInterface;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 */
#[Fillable(['name', 'starts_at', 'ends_at', 'is_current', 'is_active'])]
#[Hidden(['current_marker'])]
class AcademicYear extends Model
{
    protected $attributes = ['is_current' => false, 'is_active' => true];

    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory, InstitutionalReference;

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'is_current' => 'boolean', 'is_active' => 'boolean'];
    }

    protected function referenceAuditAttributes(): array
    {
        return ['name', 'starts_at', 'ends_at', 'is_current', 'is_active'];
    }
}
