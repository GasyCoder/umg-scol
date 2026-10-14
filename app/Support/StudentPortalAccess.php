<?php

namespace App\Support;

use App\Models\User;

class StudentPortalAccess
{
    public static function student(User $user): bool
    {
        return $user->hasRole('student') && $user->getRoleNames()->count() === 1 && $user->establishment_id === null;
    }

    public static function internal(User $user): bool
    {
        return ! $user->hasRole('student') && $user->student_id === null && $user->hasAnyRole(['superadmin', 'developer', 'central_scholarship_office', 'establishment']);
    }

    public static function audit(User $actor, string $event, ?int $student = null, ?int $request = null): void
    {
        activity('student_portal')->causedBy($actor)->performedOn($actor)->event($event)->withProperties(array_filter(['student_id' => $student, 'request_id' => $request], fn ($v) => $v !== null))->log($event);
    }
}
