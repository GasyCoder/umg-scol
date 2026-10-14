<?php

namespace App\Policies;

use App\Models\Mention;
use App\Models\User;

/** Mentions are part of the program referential: same permission and perimeter as programs. */
class MentionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('programs.manage') && $user->hasUsableEstablishment();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Mention $record): bool
    {
        return $this->viewAny($user) && (! $user->hasRole('establishment') || $user->establishment_id === $record->establishment_id);
    }
}
