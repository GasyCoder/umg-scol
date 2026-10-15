<?php

namespace App\Policies;

use App\Models\User;
use App\Support\StudentPortalAccess;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return StudentPortalAccess::internal($user) && $user->hasUsableEstablishment();
    }

    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $this->viewAny($user)
            && $notification->notifiable_type === $user->getMorphClass()
            && (int) $notification->notifiable_id === $user->id;
    }
}
