<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('programs.manage') && $user->hasUsableEstablishment();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Program $record): bool
    {
        return $this->viewAny($user) && (! $user->hasRole('establishment') || $user->establishment_id === $record->establishment_id);
    }
}
