<?php

use App\Http\Controllers\References\AcademicYearController;
use App\Http\Controllers\References\EstablishmentController;
use App\Http\Controllers\References\LevelController;
use App\Http\Controllers\References\ProgramController;
use App\Http\Middleware\EnsureEstablishmentContext;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Program;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->prefix('references')->name('references.')->group(function () {
    foreach ([
        ['establishments', 'establishment', EstablishmentController::class, Establishment::class],
        ['academic-years', 'academic_year', AcademicYearController::class, AcademicYear::class],
        ['levels', 'level', LevelController::class, Level::class],
        ['programs', 'program', ProgramController::class, Program::class],
    ] as [$uri, $parameter, $controller, $model]) {
        Route::middleware('can:viewAny,'.$model)->group(function () use ($uri, $parameter, $controller) {
            Route::resource($uri, $controller)->except(['show', 'destroy'])->parameters([$uri => $parameter]);
            Route::patch($uri.'/{'.$parameter.'}/status', [$controller, 'status'])->name($uri.'.status');
        });
    }
    Route::patch('academic-years/{academic_year}/current', [AcademicYearController::class, 'current'])->name('academic-years.current');
});
