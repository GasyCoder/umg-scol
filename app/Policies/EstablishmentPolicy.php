<?php

namespace App\Policies;

use App\Models\Establishment;
use App\Models\User;

class EstablishmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('establishments.manage') && $user->hasUsableEstablishment();
    }

    /** An establishment account only sees its own establishment. */
    public function view(User $user, Establishment $record): bool
    {
        return $this->viewAny($user) && (! $user->hasRole('establishment') || $user->establishment_id === $record->id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && ! $user->hasRole('establishment');
    }

    public function update(User $user, Establishment $record): bool
    {
        return $this->viewAny($user) && (! $user->hasRole('establishment') || $user->establishment_id === $record->id);
    }
}
