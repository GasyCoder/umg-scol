<?php

namespace App\Actions\StudentPortal;

use App\Enums\StudentLinkStatus;
use App\Models\StudentLinkRequest;
use App\Models\User;
use App\Support\StudentIdentityQuestions;
use App\Support\StudentPortalAccess;
use App\Support\StudentPortalIdentity;
use Illuminate\Support\Facades\DB;

/** Links only the official record of the OTP-verified email, after birth date and the questions drawn for it. */
class LinkStudent
{
    /** @param array<string,mixed> $answers keyed q1, q2… as issued by StudentIdentityQuestions */
    public function handle(User $actor, array $answers, string $birth): bool
    {
        return DB::transaction(function () use ($actor, $answers, $birth): bool {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(StudentPortalAccess::student($user) && $user->hasVerifiedEmail(), 403);
            $student = StudentPortalIdentity::forAccount($user, true);
            $linked = $student !== null && $student->birth_date?->format('Y-m-d') === $birth
                && StudentIdentityQuestions::passes($user, $student, $answers)
                && ! User::query()->where('student_id', $student->id)->whereKeyNot($user->id)->lockForUpdate()->exists();
            // Any refusal counts the same way, so the remaining attempts never tell which check failed.
            if (! $linked) {
                StudentIdentityQuestions::fail($user);

                return false;
            }
            StudentIdentityQuestions::forget($user);
            $alreadyLinked = $user->student_id === $student->id;
            if (! $alreadyLinked) {
                $user->forceFill(['student_id' => $student->id])->save();
                StudentLinkRequest::query()->where('user_id', $user->id)->where('status', 'pending')->update(['status' => StudentLinkStatus::Cancelled->value]);
            }
            StudentPortalAccess::audit($user, $alreadyLinked ? 'identity_verified' : 'automatic_link', $student->id);

            return true;
        }, 5);
    }
}
