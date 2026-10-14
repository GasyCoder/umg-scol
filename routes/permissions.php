<?php

use App\Http\Controllers\Permissions\PermissionController;
use App\Http\Middleware\EnsureEstablishmentContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->group(function () {
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::put('permissions/roles/{role_name}', [PermissionController::class, 'update'])->name('permissions.update');
});
