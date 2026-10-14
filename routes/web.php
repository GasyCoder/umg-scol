<?php

use App\Http\Controllers\StudentPortal\LinkReviewController;
use App\Http\Middleware\EnsureEstablishmentContext;
use App\Http\Middleware\EnsureInternalPortal;
use Illuminate\Support\Facades\Route;

Route::domain(config('portals.scol_domain'))->middleware(EnsureInternalPortal::class)->group(function () {
    Route::inertia('/', 'welcome')->name('home');

    Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->group(function () {
        Route::inertia('dashboard', 'dashboard')->name('dashboard');
    });

    require __DIR__.'/settings.php';
    require __DIR__.'/references.php';

    require __DIR__.'/students.php';

    require __DIR__.'/imports.php';
    require __DIR__.'/corrections.php';
    require __DIR__.'/scholarships.php';
    Route::get('student-links', [LinkReviewController::class, 'index'])->middleware(['auth', 'verified', EnsureEstablishmentContext::class])->name('student-links.index');
    Route::post('student-links/{student_link_request}/review', [LinkReviewController::class, 'review'])->middleware(['auth', 'verified', EnsureEstablishmentContext::class])->name('student-links.review');

});
require __DIR__.'/student-portal.php';
