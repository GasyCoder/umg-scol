<?php

use App\Http\Controllers\Scholarships\ApplicationController;
use App\Http\Controllers\Scholarships\CampaignController;
use App\Http\Controllers\Scholarships\ReceptionController;
use App\Http\Middleware\EnsureEstablishmentContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->prefix('scholarships')->name('scholarships.')->group(function () {
    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::post('campaigns/{campaign}/status', [CampaignController::class, 'transition'])->name('campaigns.status');

    Route::get('reception', [ReceptionController::class, 'index'])->name('reception');
    Route::post('applications', [ReceptionController::class, 'store'])->middleware('throttle:60,1,scholarship-receive')->name('applications.store');

    Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('applications/export', [ApplicationController::class, 'export'])->middleware('throttle:10,1,scholarship-export')->name('applications.export');
    Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::post('applications/{application}/status', [ApplicationController::class, 'transition'])->middleware('throttle:60,1,scholarship-status')->name('applications.status');
    Route::post('applications/{application}/checks', [ApplicationController::class, 'check'])->middleware('throttle:120,1,scholarship-check')->name('applications.checks');
});
