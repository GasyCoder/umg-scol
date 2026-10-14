<?php

namespace App\Policies;

use App\Models\Level;
use App\Models\User;

class LevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('levels.manage') && $user->hasUsableEstablishment();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Level $record): bool
    {
        return $this->viewAny($user);
    }
}
