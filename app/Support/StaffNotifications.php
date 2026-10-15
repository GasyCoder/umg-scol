<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Notifications\StudentActivity;
use Illuminate\Support\Facades\Notification;

/**
 * Who hears about a student's action: the active staff of the establishment that declares the
 * student this year, the central scholarship office and the superadmin, each with the permission
 * to see it. The developer stays out of operational notifications (still visible in the audit).
 */
final class StaffNotifications
{
    public static function student(Student $student, string $kind, string $message, string $subjectUuid, string $permission): void
    {
        $establishments = StudentRecord::query()->where('student_id', $student->id)->currentActive()
            ->whereHas('establishment', fn ($query) => $query->where('is_active', true))->pluck('establishment_id')->all();

        // Database-only delivery belongs to the caller's transaction: both the event and its
        // notifications commit together. A rollback (including a failed delivery) leaves neither.
        User::query()->where('is_active', true)->whereNull('student_id')->whereNotNull('email_verified_at')
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['student', 'developer']))
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['superadmin', 'central_scholarship_office', 'establishment']))
            ->where(function ($query) use ($establishments) {
                $query->whereDoesntHave('roles', fn ($role) => $role->where('name', 'establishment'))
                    ->orWhere(fn ($staff) => $staff->whereIn('establishment_id', $establishments));
            })
            ->with(['roles.permissions', 'permissions', 'deniedPermissions'])
            ->chunkById(200, function ($users) use ($student, $kind, $message, $subjectUuid, $permission) {
                $recipients = $users->filter(fn (User $user) => $user->can($permission) && $user->wantsNotification($kind));
                Notification::send($recipients, new StudentActivity($kind, $message, $student->uuid, $subjectUuid));
            });
    }
}
