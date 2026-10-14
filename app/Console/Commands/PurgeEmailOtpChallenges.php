<?php

namespace App\Console\Commands;

use App\Models\EmailOtpChallenge;
use Illuminate\Console\Command;

class PurgeEmailOtpChallenges extends Command
{
    protected $signature = 'students:purge-otp';

    protected $description = 'Supprimer les challenges OTP expirés ou consommés.';

    public function handle(): int
    {
        $count = EmailOtpChallenge::query()->where('expires_at', '<=', now())->orWhereNotNull('consumed_at')->delete();
        $this->info($count.' challenge(s) supprimé(s).');

        return self::SUCCESS;
    }
}
