<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\InstitutionalReference;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** LMD domain (ALSH, STS…), common to every establishment; mentions belong to one. */
#[Fillable(['code', 'name', 'sort_order', 'is_active'])]
class Domain extends Model
{
    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    /** @use HasFactory<DomainFactory> */
    use HasFactory, HasPublicUuid, InstitutionalReference;

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    protected function referenceAuditAttributes(): array
    {
        return ['code', 'name', 'sort_order', 'is_active'];
    }

    /** @return HasMany<Mention, $this> */
    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }
}
