<?php

namespace App\Providers;

use App\Support\PortalEmailLock;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class StudentPortalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('student-otp-request', function (Request $r) {
            $email = PortalEmailLock::key(Str::lower(trim(is_string($r->input('email')) ? $r->input('email') : '')));

            // Each challenge allows a few guesses: hourly/daily ceilings bound brute force against one address over time.
            return [Limit::perMinute(20)->by('otp-ip:'.$r->ip()), Limit::perMinute(3)->by('otp-email:'.$email), Limit::perHour(10)->by('otp-email-hour:'.$email), Limit::perDay(20)->by('otp-email-day:'.$email)];
        });
        RateLimiter::for('student-otp-verify', fn (Request $r) => [Limit::perMinute(20)->by('verify-ip:'.$r->ip()), Limit::perMinute(5)->by('verify-challenge:'.PortalEmailLock::key(is_string($r->input('challenge_id')) ? $r->input('challenge_id') : ''))]);
        RateLimiter::for('student-link', fn (Request $r) => [Limit::perMinute(10)->by('link-ip:'.$r->ip()), Limit::perMinute(3)->by('link-user:'.$r->user()->id), Limit::perDay(20)->by('link-day:'.$r->user()->id)]);
        RateLimiter::for('student-correction', fn (Request $r) => [Limit::perMinute(10)->by('correction-user:'.$r->user()->id), Limit::perDay(30)->by('correction-day:'.$r->user()->id)]);
        RateLimiter::for('student-oauth', fn (Request $r) => Limit::perMinute(10)->by('oauth:'.$r->ip()));
    }
}
