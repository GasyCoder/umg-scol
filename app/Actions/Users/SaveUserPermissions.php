<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\Permissions\PermissionCatalogue;
use App\Support\Users\InternalRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Per-account adjustments on top of the role defaults: additions (Spatie direct permissions)
 * and withdrawals (user_denied_permissions). Catalogue permissions only; Policies and
 * perimeters still apply to whatever the account ends up holding.
 */
class SaveUserPermissions
{
    /**
     * @param  list<string>  $granted  catalogue permissions added on top of the role
     * @param  list<string>  $denied  role permissions withdrawn from this account
     */
    public function handle(User $actor, User $user, array $granted, array $denied, string $version): void
    {
        Gate::forUser($actor)->authorize('managePermissions', $user);
        try {
            DB::transaction(function () use ($actor, $user, $granted, $denied, $version) {
                $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('managePermissions', $user);
                if (! hash_equals(self::version($user), $version)) {
                    throw ValidationException::withMessages(['permissions' => 'Les droits de ce compte ont changé. Actualisez la page avant d’enregistrer.']);
                }
                $role = InternalRoles::of($user);
                $rolePermissions = $role === null ? [] : Role::findByName($role, config('access.guard'))->permissions->pluck('name')->all();
                if (array_intersect($granted, $rolePermissions) !== [] || array_diff($denied, $rolePermissions) !== []) {
                    throw ValidationException::withMessages(['permissions' => 'Un droit ajouté doit être absent du rôle, un droit retiré doit en faire partie.']);
                }
                if ($role === 'superadmin' && array_intersect($denied, PermissionCatalogue::PROTECTED) !== []) {
                    throw ValidationException::withMessages(['permissions' => 'Un superadmin conserve toujours la gestion des comptes, des rôles et des permissions.']);
                }

                $currentGranted = self::granted($user);
                $currentDenied = self::denied($user);
                $added = array_values(array_diff($granted, $currentGranted));
                $removed = array_values(array_diff($currentGranted, $granted));
                $withdrawn = array_values(array_diff($denied, $currentDenied));
                $restored = array_values(array_diff($currentDenied, $denied));

                foreach ($removed as $permission) {
                    $user->revokePermissionTo($permission);
                }
                if ($added !== []) {
                    $user->givePermissionTo($added);
                }
                $user->deniedPermissions()->sync(Permission::query()->where('guard_name', config('access.guard'))->whereIn('name', $denied)->pluck('id')->mapWithKeys(fn (int $id) => [$id => ['created_at' => now()]])->all());

                if ($added !== [] || $removed !== [] || $withdrawn !== [] || $restored !== []) {
                    activity('access')->causedBy($actor)->performedOn($user)->event('user_permissions_updated')->withProperties([
                        'user_id' => $user->id, 'role_name' => $role, 'granted_permissions' => $added, 'revoked_permissions' => $removed,
                        'denied_permissions' => $withdrawn, 'restored_permissions' => $restored,
                    ])->log('user_permissions_updated');
                }
            }, 5);
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $user->unsetRelation('permissions')->unsetRelation('deniedPermissions');
        }
    }

    /** @return list<string> catalogue permissions given directly to the account */
    public static function granted(User $user): array
    {
        $names = Permission::query()->whereHas('users', fn ($query) => $query->whereKey($user->id))->get()->map(fn (Permission $permission): string => $permission->name)->all();
        $names = array_values(array_intersect($names, config('access.permissions')));
        sort($names);

        return $names;
    }

    /** @return list<string> */
    public static function denied(User $user): array
    {
        $names = $user->deniedPermissions()->get()->map(fn (Permission $permission): string => $permission->name)->all();
        sort($names);

        return $names;
    }

    /** Optimistic version of the adjustments, so a stale screen never overwrites newer ones. */
    public static function version(User $user): string
    {
        return hash('sha256', implode('|', self::granted($user)).'#'.implode('|', self::denied($user)));
    }
}
