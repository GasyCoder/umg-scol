<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\InternalAccountInvitation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;

class SendUserInvitation
{
    /** Sends a "choose your password" link; the token is the regular, single-use password reset token. */
    public function handle(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('update', $user);
        abort_unless($user->is_active, 422, 'Réactivez le compte avant de renvoyer l’invitation.');
        $token = Password::broker()->createToken($user);
        $user->notify(new InternalAccountInvitation($token));
        activity('identity')->causedBy($actor)->performedOn($user)->event('user_invitation_sent')->withProperties(['user_id' => $user->id])->log('user_invitation_sent');
    }
}
