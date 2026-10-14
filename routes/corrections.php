<?php

use App\Http\Controllers\Corrections\CorrectionController;
use App\Http\Middleware\EnsureEstablishmentContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->group(function () {
    Route::get('corrections', [CorrectionController::class, 'index'])->name('corrections.index');
    Route::get('corrections/{correction}', [CorrectionController::class, 'show'])->name('corrections.show');
    Route::post('corrections/{correction}/review', [CorrectionController::class, 'review'])->middleware('throttle:30,1')->name('corrections.review');
});
