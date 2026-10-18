<?php

use App\Http\Controllers\Platform\PlatformSettingsController;
use App\Http\Controllers\StudentPortal\AuthenticationController;
use App\Http\Controllers\StudentPortal\ConfirmationController;
use App\Http\Controllers\StudentPortal\CorrectionController;
use App\Http\Controllers\StudentPortal\NotificationController;
use App\Http\Controllers\StudentPortal\ProfileController;
use App\Http\Middleware\EnsureStudentIdentity;
use App\Http\Middleware\EnsureStudentPortal;
use Illuminate\Support\Facades\Route;

// Always bound, even while Wayfinder generates URLs: the bourse SPA does not use them, and an unbound '/' would replace the internal 'home' route.
Route::domain(config('portals.bourse_domain'))->group(function () {
    Route::view('/', 'bourse')->name('student-portal');
    // « Mes demandes » page of the same single-page portal (ADR-089).
    Route::view('demandes', 'bourse')->name('student-portal.requests');
    Route::get('branding/{asset}', [PlatformSettingsController::class, 'asset'])->whereIn('asset', ['logo', 'favicon'])->middleware('throttle:120,1,branding-asset')->name('student-portal.branding');
    Route::prefix('api/student')->name('student-api.')->group(function () {
        Route::get('session', [AuthenticationController::class, 'session'])->name('session');
        Route::post('auth/otp/request', [AuthenticationController::class, 'requestOtp'])->middleware('throttle:student-otp-request')->name('otp.request');
        Route::post('auth/otp/verify', [AuthenticationController::class, 'verifyOtp'])->middleware('throttle:student-otp-verify')->name('otp.verify');
        Route::get('auth/google/redirect', [AuthenticationController::class, 'googleRedirect'])->middleware('throttle:student-oauth')->name('google.redirect');
        Route::get('auth/google/callback', [AuthenticationController::class, 'googleCallback'])->middleware('throttle:student-oauth')->name('google.callback');
        Route::middleware(['auth:sanctum', EnsureStudentPortal::class])->group(function () {
            Route::post('logout', [AuthenticationController::class, 'logout'])->name('logout');
            Route::get('link/status', [ProfileController::class, 'status'])->name('link.status');
            Route::post('link/birth-date', [ProfileController::class, 'verifyBirth'])->middleware('throttle:student-link')->name('link.birth-date');
            Route::post('link', [ProfileController::class, 'link'])->middleware('throttle:student-link')->name('link');
            Route::middleware(EnsureStudentIdentity::class)->group(function () {
                Route::get('me', [ProfileController::class, 'me'])->name('me');
                Route::get('confirmation', [ConfirmationController::class, 'show'])->name('confirmation.show');
                Route::post('confirmation', [ConfirmationController::class, 'store'])->middleware('throttle:student-confirmation')->name('confirmation.store');
                Route::get('corrections', [CorrectionController::class, 'index'])->name('corrections.index');
                Route::post('corrections', [CorrectionController::class, 'store'])->middleware('throttle:student-correction')->name('corrections.store');
                Route::post('corrections/{correction}/cancel', [CorrectionController::class, 'cancel'])->whereUuid('correction')->name('corrections.cancel');
                Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
                Route::post('notifications/seen', [NotificationController::class, 'seen'])->name('notifications.seen');
                Route::get('messages', [NotificationController::class, 'messages'])->name('messages.index');
            });
        });
    });
});
