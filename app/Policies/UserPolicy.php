<?php

namespace App\Policies;

use App\Models\User;
use App\Support\StudentPortalAccess;

/**
 * Internal account management (`users.manage`) is global: an establishment account never
 * manages accounts, even if the matrix granted it the permission. Student accounts are
 * outside this module, and developer accounts are only visible to developers.
 */
class UserPolicy
{
    private function manager(User $actor): bool
    {
        return StudentPortalAccess::internal($actor) && $actor->hasUsableEstablishment() && ! $actor->hasRole('establishment') && $actor->can('users.manage');
    }

    private function manageable(User $actor, User $user): bool
    {
        return StudentPortalAccess::internalAccount($user) && (! $user->hasRole('developer') || $actor->hasRole('developer'));
    }

    public function viewAny(User $actor): bool
    {
        return $this->manager($actor);
    }

    public function view(User $actor, User $user): bool
    {
        return $this->manager($actor) && $this->manageable($actor, $user);
    }

    public function create(User $actor): bool
    {
        return $this->manager($actor);
    }

    /** Nobody changes their own role, status or rights here: another administrator must do it. */
    public function update(User $actor, User $user): bool
    {
        return $this->view($actor, $user) && $actor->isNot($user);
    }

    /** Per-account rights also need the right to manage permissions. */
    public function managePermissions(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && $actor->can('permissions.manage');
    }
}
