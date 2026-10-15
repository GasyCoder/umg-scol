<?php

use App\Http\Controllers\Users\UserController;
use App\Http\Middleware\EnsureEstablishmentContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->middleware('throttle:30,1,users-write')->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('throttle:30,1,users-write')->name('users.update');
    Route::patch('users/{user}/status', [UserController::class, 'status'])->middleware('throttle:30,1,users-write')->name('users.status');
    Route::put('users/{user}/permissions', [UserController::class, 'permissions'])->middleware('throttle:30,1,users-write')->name('users.permissions');
    Route::post('users/{user}/invitation', [UserController::class, 'resendInvitation'])->middleware('throttle:6,1,users-invitation')->name('users.invitation');
});
