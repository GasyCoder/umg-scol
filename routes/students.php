<?php

use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentRecordController;
use App\Http\Middleware\EnsureEstablishmentContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->group(function () {
    Route::resource('students', StudentController::class)->except('destroy');
    Route::patch('students/{student}/status', [StudentController::class, 'status'])->name('students.status');
    Route::get('students/{student}/records/create', [StudentRecordController::class, 'create'])->name('student-records.create');
    Route::post('students/{student}/records', [StudentRecordController::class, 'store'])->name('student-records.store');
    Route::get('student-records/{student_record}/edit', [StudentRecordController::class, 'edit'])->name('student-records.edit');
    Route::put('student-records/{student_record}', [StudentRecordController::class, 'update'])->name('student-records.update');
    Route::patch('student-records/{student_record}/status', [StudentRecordController::class, 'status'])->name('student-records.status');
});
