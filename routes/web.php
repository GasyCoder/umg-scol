<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Platform\PlatformSettingsController;
use App\Http\Controllers\Reporting\DashboardController;
use App\Http\Middleware\EnsureEstablishmentContext;
use App\Http\Middleware\EnsureInternalPortal;
use App\Support\PortalRouteDomain;
use Illuminate\Support\Facades\Route;

Route::domain(PortalRouteDomain::for(config('portals.scol_domain')))->middleware(EnsureInternalPortal::class)->group(function () {
    // No public landing page on the internal portal: guests land on the login page.
    Route::get('/', fn () => to_route('dashboard'))->name('home');
    Route::get('branding/{asset}', [PlatformSettingsController::class, 'asset'])->whereIn('asset', ['logo', 'favicon'])->middleware('throttle:120,1,branding-asset')->name('branding.asset');

    Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

    require __DIR__.'/settings.php';
    require __DIR__.'/references.php';

    require __DIR__.'/students.php';

    require __DIR__.'/imports.php';
    require __DIR__.'/corrections.php';
    require __DIR__.'/scholarships.php';
    require __DIR__.'/reporting.php';
    require __DIR__.'/permissions.php';
    require __DIR__.'/users.php';
    require __DIR__.'/trash.php';
    Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{notification}/open', [NotificationController::class, 'open'])->whereUuid('notification')->name('open');
        Route::patch('/{notification}', [NotificationController::class, 'read'])->whereUuid('notification')->name('read');
        Route::post('/read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::put('/preferences', [NotificationController::class, 'preferences'])->name('preferences');
    });

});
require __DIR__.'/student-portal.php';
