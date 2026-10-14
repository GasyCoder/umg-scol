<?php

namespace App\Listeners;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AuditAccessChange
{
    public function handle(RoleAttachedEvent|RoleDetachedEvent|PermissionAttachedEvent|PermissionDetachedEvent $event): void
    {
        $isRole = $event instanceof RoleAttachedEvent || $event instanceof RoleDetachedEvent;
        $items = $isRole ? $event->rolesOrIds : $event->permissionsOrIds;
        $ids = collect(is_iterable($items) ? $items : [$items])
            ->map(fn (mixed $item): mixed => $item instanceof Model ? $item->getKey() : $item)
            ->filter(fn (mixed $id): bool => is_int($id) || is_string($id))
            ->values()->all();

        if ($ids === []) {
            return;
        }

        $attached = $event instanceof RoleAttachedEvent || $event instanceof PermissionAttachedEvent;
        $action = ($isRole ? 'roles' : 'permissions').($attached ? '.attached' : '.detached');

        activity('access')
            ->performedOn($event->model)
            ->event($action)
            ->withProperties(['ids' => $ids])
            ->log($action);
    }
}
