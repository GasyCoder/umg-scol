<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class AuditAuthentication
{
    public function handle(Login|Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $action = $event instanceof Login ? 'login' : 'logout';

        activity('authentication')
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->event($action)
            ->withProperties(['guard' => $event->guard])
            ->log($action);
    }
}
