<?php

namespace App\Actions\StudentPortal;

use App\Mail\StudentOtpMail;
use App\Models\EmailOtpChallenge;
use App\Support\PortalEmailLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RequestEmailOtp
{
    public function handle(string $email, string $binding): string
    {
        $mailer = (string) config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');
        // Never send a secret through the log mailer or a failover containing it.
        abort_unless(in_array($transport, ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'sendmail'], true) || (app()->runningUnitTests() && $transport === 'array'), 503, 'Le service email est indisponible.');
        $code = (string) random_int(100000, 999999);
        $id = DB::transaction(function () use ($email, $binding, $code) {
            PortalEmailLock::acquire(['email:'.$email]);
            $key = PortalEmailLock::key('email:'.$email);
            EmailOtpChallenge::query()->where('email_key', $key)->delete();
            $challenge = EmailOtpChallenge::query()->create(['id' => (string) Str::uuid(), 'email' => $email, 'email_key' => $key, 'code_hash' => Hash::make(PortalEmailLock::key('otp:'.$code)), 'session_hash' => PortalEmailLock::key($binding), 'expires_at' => now()->addMinutes((int) config('portals.otp_minutes'))]);

            return $challenge->id;
        }, 5);
        try {
            // Queued (encrypted): the student gets the next screen at once, the worker sends the email.
            Mail::to($email)->queue(new StudentOtpMail($code));
        } catch (\Throwable) {
            EmailOtpChallenge::query()->whereKey($id)->update(['consumed_at' => now()]);
            abort(503, 'Le service email est indisponible.');
        }

        return $id;
    }
}
