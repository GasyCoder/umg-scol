<?php

use App\Notifications\StudentActivity;
use Illuminate\Foundation\Inspiring;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('students:purge-imports')->daily()->withoutOverlapping();

Schedule::command('students:purge-otp')->hourly()->withoutOverlapping();

Schedule::command('trash:cleanup-files')->hourly()->withoutOverlapping();

// Read notifications are kept 90 days; unread ones stay until read.
Artisan::command('notifications:prune-read', function () {
    $count = DatabaseNotification::query()->where('type', StudentActivity::class)
        ->whereNotNull('read_at')->where('read_at', '<', now()->subDays(90))->delete();
    $this->info($count.' notification(s) lue(s) supprimée(s).');
})->purpose('Supprimer les notifications étudiantes lues depuis plus de 90 jours');
Schedule::command('notifications:prune-read')->daily()->withoutOverlapping();
