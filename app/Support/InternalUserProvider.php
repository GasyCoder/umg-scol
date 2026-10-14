<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class InternalUserProvider extends EloquentUserProvider
{
    /** @param array<string,mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $user = parent::retrieveByCredentials($credentials);

        return $user instanceof User && StudentPortalAccess::internal($user) ? $user : null;
    }
}
