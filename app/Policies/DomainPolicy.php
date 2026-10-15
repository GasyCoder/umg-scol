<?php

namespace App\Policies;

use App\Models\Domain;
use App\Models\User;

/** Domains are national referentials like levels: same permission. */
class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('levels.manage') && $user->hasUsableEstablishment();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Domain $record): bool
    {
        return $this->viewAny($user);
    }
}
