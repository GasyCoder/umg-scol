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
use Illuminate\Support\Str;

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

    /** LMD levels every establishment uses: an import may create them when missing. code => [name, order] */
    public const STANDARD = ['L1' => ['Licence 1', 1], 'L2' => ['Licence 2', 2], 'L3' => ['Licence 3', 3], 'M1' => ['Master 1', 4], 'M2' => ['Master 2', 5], 'D1' => ['Doctorat 1', 6], 'D2' => ['Doctorat 2', 7], 'D3' => ['Doctorat 3', 8]];

    /** Standard code named by a file value ("L3", "l3", "Licence 3", "Master 1"), null otherwise. */
    public static function standardCode(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $folded = strtoupper((string) preg_replace('/\s+/u', ' ', trim(Str::ascii($value))));
        foreach (self::STANDARD as $code => [$name]) {
            if ($folded === $code || $folded === strtoupper($name)) {
                return $code;
            }
        }

        return null;
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
