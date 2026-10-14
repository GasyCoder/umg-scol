<?php

namespace App\Actions\Users;

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignUserEstablishment
{
    public function handle(User $user, ?Establishment $establishment): User
    {
        return DB::transaction(function () use ($user, $establishment): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $target = $establishment === null ? null : Establishment::query()->lockForUpdate()->findOrFail($establishment->id);
            if ($locked->hasRole('establishment') && ($target === null || ! $target->is_active)) {
                throw ValidationException::withMessages(['establishment_id' => 'Un compte établissement doit être rattaché à un établissement actif.']);
            }
            $locked->forceFill(['establishment_id' => $target?->id])->save();

            return $locked;
        });
    }
}
