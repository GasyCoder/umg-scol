<?php

namespace App\Actions\StudentPortal;

use App\Enums\StudentLinkStatus;
use App\Models\Student;
use App\Models\StudentLinkRequest;
use App\Models\User;
use App\Support\StudentNormalization;
use App\Support\StudentPortalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LinkStudent
{
    /** The student proves the link with the CIN and birth date declared by the establishment. */
    public function handle(User $actor, string $cin, string $birth): bool
    {
        return DB::transaction(function () use ($actor, $cin, $birth) {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(StudentPortalAccess::student($user) && $user->hasVerifiedEmail(), 403);
            if ($user->student_id !== null) {
                return true;
            }
            $cin = StudentNormalization::value('cin', $cin);
            $candidates = Student::query()->where('cin', $cin)->whereDate('birth_date', $birth)->lockForUpdate()->limit(2)->get();
            // Reject collated near matches too: never choose one identity from an ambiguous result.
            if ($candidates->count() !== 1) {
                return false;
            }
            $student = $candidates->first();
            if ($student->cin !== $cin || ! $student->is_active || User::query()->where('student_id', $student->id)->lockForUpdate()->first() !== null) {
                return false;
            }
            if (filled($student->email) && Str::lower($student->email) === Str::lower($user->email)) {
                $user->forceFill(['student_id' => $student->id])->save();
                StudentLinkRequest::query()->where('user_id', $user->id)->where('status', 'pending')->update(['status' => StudentLinkStatus::Cancelled->value]);
                StudentPortalAccess::audit($user, 'automatic_link', $student->id);

                return true;
            }
            $existing = StudentLinkRequest::query()->where('user_id', $user->id)->where('student_id', $student->id)->first();
            if ($existing !== null) {
                return false;
            }
            StudentLinkRequest::query()->where('user_id', $user->id)->where('status', 'pending')->update(['status' => StudentLinkStatus::Cancelled->value]);
            $request = StudentLinkRequest::query()->create(['user_id' => $user->id, 'student_id' => $student->id, 'status' => StudentLinkStatus::Pending, 'requested_at' => now()]);
            StudentPortalAccess::audit($user, 'link_requested', $student->id, $request->id);

            return false;
        }, 5);
    }
}
