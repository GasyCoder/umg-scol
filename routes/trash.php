<?php

use App\Http\Controllers\Trash\TrashController;
use App\Http\Middleware\EnsureEstablishmentContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->group(function () {
    Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
    Route::delete('trash/{kind}/{uuid}', [TrashController::class, 'move'])->name('trash.move');
    Route::post('trash/{kind}/{uuid}/restore', [TrashController::class, 'restore'])->name('trash.restore');
    Route::delete('trash/{kind}/{uuid}/purge', [TrashController::class, 'purge'])->name('trash.purge');
});
