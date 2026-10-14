<?php

namespace App\Models;

use App\Models\Concerns\InstitutionalReference;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['establishment_id', 'code', 'name', 'is_active'])]
class Program extends Model
{
    protected $attributes = ['is_active' => true];

    /** @use HasFactory<ProgramFactory> */
    use HasFactory, InstitutionalReference;

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
}
