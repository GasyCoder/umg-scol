<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait InstitutionalReference
{
    use LogsActivity;

    protected static function bootInstitutionalReference(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Un référentiel doit être désactivé, pas supprimé.');
        });
    }

    /** @return Attribute<string|null, string|null> */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => filled($value) ? strtoupper(trim($value)) : null);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('references')
            ->logOnly($this->referenceAuditAttributes())->logOnlyDirty()->logEmptyChanges();
    }

    /** @return list<string> */
    abstract protected function referenceAuditAttributes(): array;
}
