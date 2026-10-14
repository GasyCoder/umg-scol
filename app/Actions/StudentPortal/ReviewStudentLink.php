<?php

namespace App\Actions\StudentPortal;

use App\Enums\StudentLinkStatus;
use App\Models\Student;
use App\Models\StudentLinkRequest;
use App\Models\User;
use App\Support\StudentPortalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewStudentLink
{
    public function handle(User $actor, StudentLinkRequest $request, bool $approve, ?string $reason): void
    {
        Gate::forUser($actor)->authorize('review', $request);
        DB::transaction(function () use ($actor, $request, $approve, $reason) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user_id);
            $student = Student::query()->lockForUpdate()->findOrFail($request->student_id);
            $target = StudentLinkRequest::query()->lockForUpdate()->findOrFail($request->id);
            Gate::forUser($actor)->authorize('review', $target);
            if ($approve && $target->status === StudentLinkStatus::Approved && $user->student_id === $student->id && StudentPortalAccess::student($user)) {
                return;
            }
            if ($target->status !== StudentLinkStatus::Pending || ! StudentPortalAccess::student($user) || ! $user->hasVerifiedEmail()) {
                $this->fail();
            }
            if ($approve) {
                if (! $student->is_active || $user->student_id !== null || User::query()->where('student_id', $student->id)->lockForUpdate()->first() !== null) {
                    $this->fail();
                }
                $user->forceFill(['student_id' => $student->id])->save();
            } elseif (blank($reason)) {
                throw ValidationException::withMessages(['rejection_reason' => 'Un motif interne est obligatoire.']);
            }
            $target->update(['status' => $approve ? StudentLinkStatus::Approved : StudentLinkStatus::Rejected, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'rejection_reason' => $approve ? null : $reason]);
            StudentPortalAccess::audit($actor, $approve ? 'link_approved' : 'link_rejected', $student->id, $target->id);
        }, 5);
    }

    private function fail(): never
    {
        throw ValidationException::withMessages(['request' => 'Cette demande ne peut pas être traitée. Actualisez la liste.']);
    }
}
