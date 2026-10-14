<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\InstitutionalReference;
use App\Models\Concerns\Trashable;
use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'sort_order', 'is_active'])]
class Level extends Model
{
    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    /** @use HasFactory<LevelFactory> */
    use HasFactory, HasPublicUuid, InstitutionalReference, Trashable;

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    protected function referenceAuditAttributes(): array
    {
        return ['code', 'name', 'sort_order', 'is_active'];
    }

    /** @return HasMany<StudentRecord, $this> */
    public function studentRecords(): HasMany
    {
        return $this->hasMany(StudentRecord::class);
    }
}
