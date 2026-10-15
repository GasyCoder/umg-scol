<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Notifications\StudentActivity;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveNotificationPreferences
{
    /** @param list<string> $enabled */
    public function handle(User $actor, array $enabled): void
    {
        Gate::forUser($actor)->authorize('viewAny', DatabaseNotification::class);
        Validator::make(['enabled' => $enabled], ['enabled' => ['array'], 'enabled.*' => ['string', 'distinct', Rule::in(array_keys(StudentActivity::TYPES))]])->validate();
        $off = array_values(array_diff(array_keys(StudentActivity::TYPES), $enabled));
        DB::transaction(function () use ($actor, $off) {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (($user->notification_preferences['off'] ?? []) === $off) {
                return;
            }
            $user->forceFill(['notification_preferences' => ['off' => $off]])->save();
            activity('notifications')->causedBy($actor)->performedOn($user)->event('notification_preferences_updated')
                ->withProperties(['disabled_types' => $off])->log('notification_preferences_updated');
        });
    }
}
