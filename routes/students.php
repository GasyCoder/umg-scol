<?php

use App\Http\Controllers\Students\StudentBulkController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentNotificationController;
use App\Http\Controllers\Students\StudentRecordController;
use App\Http\Middleware\EnsureEstablishmentContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureEstablishmentContext::class])->group(function () {
    // Before the resource: "template" and "export" are not student uuids.
    Route::get('students/template', [StudentController::class, 'template'])->middleware('throttle:20,1,students-export')->name('students.template');
    Route::get('students/export', [StudentController::class, 'export'])->middleware('throttle:20,1,students-export')->name('students.export');
    Route::post('students/export', [StudentController::class, 'exportSelection'])->middleware('throttle:20,1,students-export')->name('students.export-selection');
    Route::post('students/bulk', StudentBulkController::class)->middleware('throttle:30,1,students-bulk')->name('students.bulk');
    Route::post('students/notifications', [StudentNotificationController::class, 'bulk'])->middleware('throttle:10,1,students-notify-bulk')->name('students.notifications.bulk');
    Route::resource('students', StudentController::class)->except('destroy');
    Route::patch('students/{student}/status', [StudentController::class, 'status'])->name('students.status');
    Route::post('students/{student}/notifications', [StudentNotificationController::class, 'store'])->middleware('throttle:30,1,students-notify')->name('students.notifications.store');
    Route::delete('students/{student}/portal', [StudentController::class, 'detachPortal'])->name('students.portal.detach');
    Route::get('students/{student}/records/create', [StudentRecordController::class, 'create'])->name('student-records.create');
    Route::post('students/{student}/records', [StudentRecordController::class, 'store'])->name('student-records.store');
    Route::get('student-records/{student_record}/edit', [StudentRecordController::class, 'edit'])->name('student-records.edit');
    Route::put('student-records/{student_record}', [StudentRecordController::class, 'update'])->name('student-records.update');
    Route::patch('student-records/{student_record}/status', [StudentRecordController::class, 'status'])->name('student-records.status');
    Route::post('student-records/{student_record}/verify', [StudentRecordController::class, 'verify'])->name('student-records.verify');
});
