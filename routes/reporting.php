<?php

use App\Http\Controllers\Reporting\AuditController;
use App\Http\Controllers\Reporting\ReportController;
use App\Http\Middleware\EnsureEstablishmentContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}/export', [ReportController::class, 'export'])->whereIn('report', ['students', 'imports', 'scholarships'])->middleware('throttle:10,1,report-export')->name('reports.export');
    Route::get('audits', [AuditController::class, 'index'])->name('audits.index');
    Route::get('audits/export', [AuditController::class, 'export'])->middleware('throttle:10,1,audit-export')->name('audits.export');
    Route::get('audits/{activity}', [AuditController::class, 'show'])->whereUuid('activity')->name('audits.show');
});
