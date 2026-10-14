<?php

namespace App\Actions\StudentPortal;

use App\Models\User;
use App\Support\StudentPortalAccess;
use Illuminate\Support\Str;

class StudentAccount
{
    public function find(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
    }

    public function create(string $email): User
    {
        $user = User::query()->create(['name' => 'Compte étudiant', 'email' => $email, 'password' => Str::random(64)]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('student');
        StudentPortalAccess::audit($user, 'account_created');

        return $user;
    }

    public function verified(string $email): ?User
    {
        $user = $this->find($email);
        if ($user === null) {
            return $this->create($email);
        }
        if (! StudentPortalAccess::student($user)) {
            return null;
        }
        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }
}
