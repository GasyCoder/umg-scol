<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\InstitutionalReference;
use App\Models\Concerns\Trashable;
use Database\Factories\EstablishmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'address', 'phone', 'email', 'is_active'])]
class Establishment extends Model
{
    protected $attributes = ['is_active' => true];

    /** @use HasFactory<EstablishmentFactory> */
    use HasFactory, HasPublicUuid, InstitutionalReference, Trashable;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function referenceAuditAttributes(): array
    {
        return ['code', 'name', 'type', 'is_active'];
    }

    /** @return HasMany<Mention, $this> */
    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }

    /** @return HasMany<Program, $this> */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<StudentRecord, $this> */
    public function studentRecords(): HasMany
    {
        return $this->hasMany(StudentRecord::class);
    }
}
