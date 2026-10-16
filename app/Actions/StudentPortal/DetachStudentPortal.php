<?php

namespace App\Actions\StudentPortal;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Staff undo of a portal link (wrong person, lost account): the account keeps existing but no
 * longer sees the record; the right student links again with the OTP and its information.
 */
class DetachStudentPortal
{
    public function handle(User $actor, Student $student): void
    {
        Gate::forUser($actor)->authorize('view', $student);
        abort_unless($actor->can('student_links.review'), 403);
        DB::transaction(function () use ($actor, $student): void {
            $account = User::query()->where('student_id', $student->id)->lockForUpdate()->first();
            if ($account === null) {
                return;
            }
            $account->forceFill(['student_id' => null])->save();
            activity('student_portal')->causedBy($actor)->performedOn($account)->event('portal_detached')
                ->withProperties(['student_id' => $student->id])->log('portal_detached');
        });
    }
}
