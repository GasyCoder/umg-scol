<?php

namespace App\Actions\Permissions;

use App\Models\User;
use App\Support\Permissions\PermissionCatalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SaveRolePermissions
{
    /** @param list<string> $permissions */
    public function handle(User $actor, string $name, array $permissions, string $version): void
    {
        Gate::forUser($actor)->authorize('permissions.manage-ui');
        // Unknown role, or the developer role for an actor who is not a developer: it does not exist here.
        abort_unless(in_array($name, PermissionCatalogue::visibleRoles($actor), true), 404);
        if (array_diff($permissions, config('access.permissions')) !== [] || ($name === 'student' && array_diff($permissions, PermissionCatalogue::STUDENT) !== []) || ($name === 'superadmin' && array_diff(PermissionCatalogue::PROTECTED, $permissions) !== [])) {
            throw ValidationException::withMessages(['permissions' => 'Cette modification est interdite : protégez les droits du superadmin et limitez le rôle étudiant à son portail.']);
        }
        try {
            DB::transaction(function () use ($actor, $name, $permissions, $version) {
                $role = Role::query()->where('name', $name)->where('guard_name', config('access.guard'))->lockForUpdate()->firstOrFail();
                $current = Permission::query()->whereHas('roles', fn ($query) => $query->whereKey($role->id))->get()->map(fn (Permission $p): string => $p->name)->sort()->values()->all();
                if (! hash_equals(PermissionCatalogue::version($current), $version)) {
                    throw ValidationException::withMessages(['permissions' => 'La matrice a changé. Actualisez la page avant d’enregistrer.']);
                }
                $managed = array_values(array_intersect($current, config('access.permissions')));
                $granted = array_values(array_diff($permissions, $managed));
                $revoked = array_values(array_diff($managed, $permissions));
                foreach ($revoked as $permission) {
                    $role->revokePermissionTo($permission);
                }
                if ($granted !== []) {
                    $role->givePermissionTo($granted);
                }
                if ($granted !== [] || $revoked !== []) {
                    activity('access')->causedBy($actor)->performedOn($role)->event('role_permissions_updated')->withProperties(['role_name' => $name, 'granted_permissions' => $granted, 'revoked_permissions' => $revoked])->log('role_permissions_updated');
                }
            }, 5);
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $actor->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
