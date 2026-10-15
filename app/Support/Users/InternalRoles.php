<?php

namespace App\Support\Users;

use App\Models\User;

/** Internal roles that an account can receive from the user management screens. */
final class InternalRoles
{
    public const ROLES = ['superadmin', 'central_scholarship_office', 'establishment', 'developer'];

    /** @return list<string> */
    public static function assignableBy(User $actor): array
    {
        $roles = [];
        foreach (self::ROLES as $role) {
            if ($role !== 'developer' || $actor->hasRole('developer')) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /** The single internal role of an account (accounts carry exactly one role here). */
    public static function of(User $user): ?string
    {
        return $user->roles->pluck('name')->first(fn (string $role) => in_array($role, self::ROLES, true));
    }
}
