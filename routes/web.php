<?php

use App\Http\Controllers\Reporting\DashboardController;
use App\Http\Controllers\StudentPortal\LinkReviewController;
use App\Http\Middleware\EnsureEstablishmentContext;
use App\Http\Middleware\EnsureInternalPortal;
use App\Support\PortalRouteDomain;
use Illuminate\Support\Facades\Route;

Route::domain(PortalRouteDomain::for(config('portals.scol_domain')))->middleware(EnsureInternalPortal::class)->group(function () {
    // No public landing page on the internal portal: guests land on the login page.
    Route::get('/', fn () => to_route('dashboard'))->name('home');

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
    Route::get('student-links', [LinkReviewController::class, 'index'])->middleware(['auth', 'verified', EnsureEstablishmentContext::class])->name('student-links.index');
    Route::post('student-links/{student_link_request}/review', [LinkReviewController::class, 'review'])->middleware(['auth', 'verified', EnsureEstablishmentContext::class])->name('student-links.review');

});
require __DIR__.'/student-portal.php';
