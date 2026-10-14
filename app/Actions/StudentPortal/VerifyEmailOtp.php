<?php

namespace App\Actions\StudentPortal;

use App\Models\EmailOtpChallenge;
use App\Models\User;
use App\Support\PortalEmailLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VerifyEmailOtp
{
    public function handle(string $id, string $code, string $binding): User
    {
        $user = DB::transaction(function () use ($id, $code, $binding) {
            $snapshot = EmailOtpChallenge::query()->find($id);
            if ($snapshot === null) {
                return null;
            }
            PortalEmailLock::acquire(['email:'.$snapshot->email]);
            $challenge = EmailOtpChallenge::query()->whereKey($id)->lockForUpdate()->first();
            if ($challenge === null || $challenge->consumed_at !== null || $challenge->expires_at->isPast() || $challenge->attempts >= (int) config('portals.otp_attempts') || ! hash_equals($challenge->session_hash, PortalEmailLock::key($binding))) {
                return null;
            }
            $challenge->increment('attempts');
            if (! Hash::check(PortalEmailLock::key('otp:'.$code), $challenge->code_hash)) {
                return null;
            }
            $challenge->update(['consumed_at' => now()]);

            return app(StudentAccount::class)->verified($challenge->email);
        }, 5);
        if ($user === null) {
            throw ValidationException::withMessages(['code' => 'Code invalide ou expiré. Demandez un nouveau code si nécessaire.']);
        }

        return $user;
    }
}
