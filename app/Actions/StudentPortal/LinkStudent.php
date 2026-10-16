<?php

namespace App\Actions\StudentPortal;

use App\Enums\StudentLinkStatus;
use App\Mail\StudentPortalLinkedMail;
use App\Models\Student;
use App\Models\StudentLinkRequest;
use App\Models\User;
use App\Support\StudentImportColumns;
use App\Support\StudentNormalization;
use App\Support\StudentPortalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Links a student account (email proven by the OTP or Google) to the record declared by the
 * establishment, at once and without staff approval, when the student proves the record:
 * birth date + one identifier (CIN, phone or email declared by the establishment), or, with no
 * such identifier, last name + mother's name. A single active, unlinked record must match.
 * Protections instead of a manual review: one account per record, generic answer, rate limit,
 * an alert to the official email of the record, and staff can detach a wrong link.
 */
class LinkStudent
{
    public const IDENTIFIERS = ['cin', 'phone', 'email', 'last_name'];

    public function handle(User $actor, string $type, string $identifier, string $birth, ?string $motherName = null): bool
    {
        abort_unless(in_array($type, self::IDENTIFIERS, true), 422);

        $linked = DB::transaction(function () use ($actor, $type, $identifier, $birth, $motherName): ?Student {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(StudentPortalAccess::student($user) && $user->hasVerifiedEmail(), 403);
            if ($user->student_id !== null) {
                return null;
            }
            $value = (string) StudentNormalization::value($type, $identifier);
            $fold = fn (?string $text) => StudentImportColumns::fold((string) preg_replace('/\s+/u', ' ', (string) $text));
            $same = fn (Student $student) => $type === 'last_name'
                // The name alone is too easy to know: the mother's name of the record is asked too.
                ? $fold($student->last_name) === $fold($value) && filled($student->mother_name) && filled($motherName) && $fold($student->mother_name) === $fold($motherName)
                : $student->getAttribute($type) === $value;
            $candidates = Student::query()->whereDate('birth_date', $birth)->when($type !== 'last_name', fn ($q) => $q->where($type, $value))
                ->lockForUpdate()->limit($type === 'last_name' ? 500 : 2)->get()->filter($same)->values();
            // Never choose one identity from an ambiguous result.
            if ($candidates->count() !== 1) {
                return null;
            }
            $student = $candidates->first();
            if (! $student->is_active || User::query()->where('student_id', $student->id)->lockForUpdate()->first() !== null) {
                return null;
            }
            $user->forceFill(['student_id' => $student->id])->save();
            // Requests left from the former manual review are closed.
            StudentLinkRequest::query()->where('user_id', $user->id)->where('status', 'pending')->update(['status' => StudentLinkStatus::Cancelled->value]);
            StudentPortalAccess::audit($user, 'automatic_link', $student->id);

            return $student;
        }, 5);
        if ($linked === null) {
            return false;
        }
        // Alert the official address when it is not the one of the account: a wrong link is noticed.
        if (filled($linked->email) && Str::lower($linked->email) !== Str::lower($actor->email)) {
            Mail::to($linked->email)->queue(new StudentPortalLinkedMail($actor->email));
        }

        return true;
    }
}
