<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\Users\InternalRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SaveInternalUser
{
    /**
     * Creates or updates an internal account with exactly one role. A new account gets an
     * unusable random password; the caller then sends the invitation (SendUserInvitation).
     *
     * @param  array{name:string,email:string,role:string,establishment_id:int|null}  $data
     */
    public function handle(User $actor, ?User $user, array $data): User
    {
        $gate = Gate::forUser($actor);
        $user === null ? $gate->authorize('create', User::class) : $gate->authorize('update', $user);
        if (! in_array($data['role'], InternalRoles::assignableBy($actor), true)) {
            throw ValidationException::withMessages(['role' => 'Ce rôle ne peut pas être attribué depuis votre compte.']);
        }
        $establishmentId = $data['role'] === 'establishment' ? $data['establishment_id'] : null;

        try {
            $saved = DB::transaction(fn () => $user === null
                ? $this->create($actor, $data, $establishmentId)
                : $this->update($actor, $user, $data, $establishmentId), 5);
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $saved;
    }

    /** @param array{name:string,email:string,role:string,establishment_id:int|null} $data */
    private function create(User $actor, array $data, ?int $establishmentId): User
    {
        $user = new User;
        // The administrator vouches for the address; the invitation link then proves it.
        $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::random(64), 'email_verified_at' => now(), 'establishment_id' => $establishmentId, 'is_active' => true])->save();
        $user->assignRole($data['role']);
        activity('identity')->causedBy($actor)->performedOn($user)->event('user_created')->withProperties(['role_name' => $data['role'], 'establishment_id' => $establishmentId])->log('user_created');

        return $user;
    }

    /** @param array{name:string,email:string,role:string,establishment_id:int|null} $data */
    private function update(User $actor, User $user, array $data, ?int $establishmentId): User
    {
        $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('update', $user);
        $previous = InternalRoles::of($user);
        if ($previous === 'superadmin' && $data['role'] !== 'superadmin') {
            self::ensureAnotherActiveSuperadmin($user);
        }
        $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'establishment_id' => $establishmentId]);
        if ($user->isDirty('email')) {
            $user->forceFill(['email_verified_at' => now()]);
        }
        $changed = array_values(array_intersect(['name', 'email', 'establishment_id'], array_keys($user->getDirty())));
        $user->save();
        if ($previous !== $data['role']) {
            $user->syncRoles([$data['role']]);
            self::alignOverrides($user, $data['role']);
        }
        if ($changed !== [] || $previous !== $data['role']) {
            activity('identity')->causedBy($actor)->performedOn($user)->event('user_updated')->withProperties(['changed_fields' => $changed, 'role_name' => $data['role'], 'previous_role' => $previous])->log('user_updated');
        }

        return $user;
    }

    /** Called under lock: an active superadmin must always remain to administer the platform. */
    public static function ensureAnotherActiveSuperadmin(User $user): void
    {
        $others = User::role('superadmin')->where('is_active', true)->whereKeyNot($user->id)->lockForUpdate()->count();
        if ($others === 0) {
            throw ValidationException::withMessages(['role' => 'Au moins un superadmin actif doit rester sur la plateforme.']);
        }
    }

    /** After a role change, drop withdrawals outside the new role and additions it now includes. */
    private static function alignOverrides(User $user, string $role): void
    {
        $rolePermissions = Role::findByName($role, config('access.guard'))->permissions->pluck('id')->all();
        $stale = $user->deniedPermissions()->whereNotIn('permissions.id', $rolePermissions)->pluck('permissions.id')->all();
        if ($stale !== []) {
            $user->deniedPermissions()->detach($stale);
        }
        foreach ($user->permissions()->whereIn('permissions.id', $rolePermissions)->get() as $permission) {
            $user->revokePermissionTo($permission);
        }
        $user->unsetRelation('deniedPermissions')->unsetRelation('permissions');
    }
}
