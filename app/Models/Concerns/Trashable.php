<?php

namespace App\Models\Concerns;

use App\Support\Trash\TrashManager;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Soft delete through the trash only: a plain delete() or forceDelete() outside the TrashManager
 * is refused, so administrative data never disappears by accident and every removal is checked and audited.
 *
 * @property Carbon|null $deleted_at
 * @property int|null $deleted_by
 */
trait Trashable
{
    use SoftDeletes;

    protected static function bootTrashable(): void
    {
        static::restoring(function (): void {
            if (! TrashManager::isRunning()) {
                throw new LogicException('La restauration doit passer par la corbeille.');
            }
        });
        static::deleting(function (): void {
            if (! TrashManager::isRunning()) {
                throw new LogicException('Cette donnée administrative ne se supprime que par la corbeille.');
            }
        });
    }
}
