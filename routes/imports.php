<?php

use App\Http\Controllers\Imports\StudentImportController;
use App\Http\Middleware\EnsureEstablishmentContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->prefix('imports')->name('imports.')->group(function () {
    Route::get('/', [StudentImportController::class, 'index'])->name('index');
    Route::get('/create', [StudentImportController::class, 'create'])->name('create');
    Route::get('/template', [StudentImportController::class, 'template'])->name('template');
    Route::post('/', [StudentImportController::class, 'store'])->name('store');
    Route::get('/{student_import}', [StudentImportController::class, 'show'])->name('show');
    Route::post('/{student_import}/analyze', [StudentImportController::class, 'analyze'])->name('analyze');
    Route::post('/{student_import}/commit', [StudentImportController::class, 'commit'])->name('commit');
    Route::get('/{student_import}/errors', [StudentImportController::class, 'exportErrors'])->name('errors');
});
