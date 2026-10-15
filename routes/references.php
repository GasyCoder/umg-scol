<?php

use App\Http\Controllers\References\AcademicYearController;
use App\Http\Controllers\References\DomainController;
use App\Http\Controllers\References\EstablishmentController;
use App\Http\Controllers\References\LevelController;
use App\Http\Controllers\References\MentionController;
use App\Http\Controllers\References\ProgramController;
use App\Http\Controllers\References\ReferenceTransferController;
use App\Http\Middleware\EnsureEstablishmentContext;
use App\Models\AcademicYear;
use App\Models\Domain;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Support\References\ReferenceImportStore;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->prefix('references')->name('references.')->group(function () {
    foreach ([
        ['establishments', 'establishment', EstablishmentController::class, Establishment::class],
        ['academic-years', 'academic_year', AcademicYearController::class, AcademicYear::class],
        ['levels', 'level', LevelController::class, Level::class],
        ['domains', 'domain', DomainController::class, Domain::class],
        ['mentions', 'mention', MentionController::class, Mention::class],
        ['programs', 'program', ProgramController::class, Program::class],
    ] as [$uri, $parameter, $controller, $model]) {
        Route::middleware('can:viewAny,'.$model)->group(function () use ($uri, $parameter, $controller) {
            Route::resource($uri, $controller)->except(['show', 'destroy'])->parameters([$uri => $parameter]);
            Route::patch($uri.'/{'.$parameter.'}/status', [$controller, 'status'])->name($uri.'.status');
        });
    }
    Route::get('establishments/{establishment}/logo', [EstablishmentController::class, 'logo'])->middleware('can:viewAny,'.Establishment::class)->name('establishments.logo');
    Route::patch('academic-years/{academic_year}/current', [AcademicYearController::class, 'current'])->name('academic-years.current');

    // Mention/program spreadsheets: model, export and previewed import (authorization in the controller, per kind).
    Route::controller(ReferenceTransferController::class)->prefix('{kind}')
        ->where(['kind' => 'mentions|programs', 'token' => ReferenceImportStore::TOKEN_PATTERN])->name('transfer.')->group(function () {
            Route::get('template', 'template')->middleware('throttle:30,1,reference-transfer')->name('template');
            Route::get('export', 'export')->middleware('throttle:30,1,reference-transfer')->name('export');
            Route::post('import', 'analyze')->middleware('throttle:10,1,reference-import')->name('analyze');
            Route::get('import/{token}', 'preview')->name('preview');
            Route::post('import/{token}', 'confirm')->middleware('throttle:10,1,reference-import')->name('confirm');
        });
});
