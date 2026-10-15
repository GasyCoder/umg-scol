<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SetUserActive
{
    /** Suspension closes open sessions and remember-me cookies; nothing is deleted. */
    public function handle(User $actor, User $user, bool $active): void
    {
        Gate::forUser($actor)->authorize('update', $user);
        DB::transaction(function () use ($actor, $user, $active) {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->is_active === $active) {
                return;
            }
            if (! $active && $user->hasRole('superadmin')) {
                SaveInternalUser::ensureAnotherActiveSuperadmin($user);
            }
            $user->forceFill(['is_active' => $active]);
            if (! $active) {
                $user->setRememberToken(Str::random(60));
            }
            $user->disableLogging()->save();
            $user->enableLogging();
            // Database sessions are closed now; with other drivers EnsureInternalPortal ends them on the next request.
            if (! $active && config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            $event = $active ? 'user_reactivated' : 'user_suspended';
            activity('identity')->causedBy($actor)->performedOn($user)->event($event)->withProperties(['user_id' => $user->id, 'is_active' => $active])->log($event);
        });
    }
}
