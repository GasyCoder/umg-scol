<?php

namespace App\Actions\StudentPortal;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\PortalEmailLock;
use App\Support\StudentPortalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as GoogleUser;

class AuthenticateGoogleStudent
{
    public function handle(GoogleUser $profile, ?User $current): User
    {
        $email = Str::lower(trim((string) $profile->getEmail()));
        $subject = (string) $profile->getId();
        $raw = $profile->getRaw();
        if (($raw['email_verified'] ?? false) !== true || ! preg_match('/^[0-9]{1,191}$/', $subject) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $this->fail();
        }

        return DB::transaction(function () use ($email, $subject, $current) {
            PortalEmailLock::acquire(['email:'.$email, 'google:'.$subject]);
            $social = SocialAccount::query()->where('provider', 'google')->where('provider_user_id', $subject)->lockForUpdate()->first();
            if ($social !== null) {
                $user = User::query()->lockForUpdate()->findOrFail($social->user_id);
                if (! StudentPortalAccess::student($user) || ! $user->hasVerifiedEmail() || Str::lower($user->email) !== $email || ($current !== null && $current->id !== $user->id)) {
                    $this->fail();
                }

                return $user;
            }
            $user = app(StudentAccount::class)->find($email);
            if ($user !== null) {
                // Existing account: possession of a verified Google email alone never attaches a provider.
                if ($current === null || $current->id !== $user->id || ! StudentPortalAccess::student($user) || ! $user->hasVerifiedEmail()) {
                    $this->fail();
                }
            } else {
                if ($current !== null) {
                    $this->fail();
                } $user = app(StudentAccount::class)->create($email);
            }
            if (SocialAccount::query()->where('user_id', $user->id)->where('provider', 'google')->exists()) {
                $this->fail();
            }
            SocialAccount::query()->create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => $subject, 'provider_email' => $email]);
            StudentPortalAccess::audit($user, 'google_associated');

            return $user;
        }, 5);
    }

    private function fail(): never
    {
        throw ValidationException::withMessages(['google' => 'Connexion Google impossible. Utilisez email + code ; vous pourrez ensuite associer Google depuis votre session.']);
    }
}
