<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\InstitutionalReference;
use App\Models\Concerns\Trashable;
use Database\Factories\MentionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mention of an establishment (LMD), grouping its programs. */
#[Fillable(['establishment_id', 'code', 'name', 'is_active'])]
class Mention extends Model
{
    protected $attributes = ['is_active' => true];

    /** @use HasFactory<MentionFactory> */
    use HasFactory, HasPublicUuid, InstitutionalReference, Trashable;

    protected function casts(): array
    {
        return ['establishment_id' => 'integer', 'is_active' => 'boolean'];
    }

    protected function referenceAuditAttributes(): array
    {
        return ['establishment_id', 'code', 'name', 'is_active'];
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return HasMany<Program, $this> */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
