<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Public, non-guessable identifier (UUID v7) used in URLs.
 * The integer id stays the primary key and the target of every foreign key.
 *
 * @property string $uuid
 */
trait HasPublicUuid
{
    use HasUuids;

    /**
     * Only the public uuid is generated; the integer id stays the primary key.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** URLs use the uuid, never the sequential id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
