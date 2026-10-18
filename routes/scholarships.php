<?php

use App\Http\Controllers\Scholarships\ApplicationController;
use App\Http\Controllers\Scholarships\ReceptionController;
use App\Http\Controllers\Scholarships\ReceptionLabelController;
use App\Http\Middleware\EnsureEstablishmentContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->prefix('scholarships')->name('scholarships.')->group(function () {

    Route::get('reception', [ReceptionController::class, 'index'])->name('reception');
    Route::get('reception/labels', [ReceptionLabelController::class, 'show'])->name('reception.labels');
    Route::get('reception/students/{student}', [ReceptionController::class, 'showStudent'])->name('reception.students.show');
    Route::put('reception/students/{student}', [ReceptionController::class, 'updateStudent'])->middleware('throttle:60,1,reception-student')->name('reception.students.update');
    Route::post('applications', [ReceptionController::class, 'store'])->middleware('throttle:60,1,scholarship-receive')->name('applications.store');

    Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::post('applications/bulk', [ApplicationController::class, 'bulk'])->middleware('throttle:20,1,scholarship-bulk')->name('applications.bulk');
    Route::get('applications/export', [ApplicationController::class, 'export'])->middleware('throttle:10,1,scholarship-export')->name('applications.export');
    Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::post('applications/{application}/status', [ApplicationController::class, 'transition'])->middleware('throttle:60,1,scholarship-status')->name('applications.status');
    Route::post('applications/{application}/checks', [ApplicationController::class, 'check'])->middleware('throttle:120,1,scholarship-check')->name('applications.checks');
});
