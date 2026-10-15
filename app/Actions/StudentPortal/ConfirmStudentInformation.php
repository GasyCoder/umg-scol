<?php

namespace App\Actions\StudentPortal;

use App\Models\Student;
use App\Models\StudentInformationConfirmation;
use App\Models\User;
use App\Notifications\StudentActivity;
use App\Support\StaffNotifications;
use App\Support\StudentConfirmationContext;
use App\Support\StudentInformationFingerprint;
use App\Support\StudentPortalAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmStudentInformation
{
    /**
     * Records that the student checked the official information currently shown and finds it correct.
     * Nothing is taken from the client except the opaque token of the displayed version: the student,
     * declaration, year and fingerprint are derived from the account and computed under lock.
     */
    public function handle(User $actor, string $version, bool $retry = true): StudentConfirmationContext
    {
        try {
            return DB::transaction(function () use ($actor, $version) {
                // Same lock order as correction requests (account, then student): a concurrent request is serialized.
                $user = User::query()->lockForUpdate()->findOrFail($actor->id);
                abort_unless(StudentPortalAccess::student($user) && $user->hasVerifiedEmail() && $user->student_id !== null, 403);
                $student = Student::query()->whereKey($user->student_id)->where('is_active', true)->lockForUpdate()->first();
                abort_if($student === null, 403, 'Le compte ne permet pas actuellement de confirmer une fiche.');
                $context = StudentConfirmationContext::load($student, lock: true);
                if (! $context->canConfirm()) {
                    throw ValidationException::withMessages(['confirmation' => $context->message()]);
                }
                $fingerprint = (string) $context->fingerprint();
                if (! hash_equals(StudentInformationFingerprint::viewToken($fingerprint), $version)) {
                    throw ValidationException::withMessages(['version' => 'Vos informations ont changé depuis leur affichage. Vérifiez-les à nouveau avant de confirmer.']);
                }
                // Repeated click or retry on the same version: no new confirmation, no new audit line.
                if ($context->status() === StudentConfirmationContext::CONFIRMED) {
                    return $context;
                }
                $record = $context->record;
                $confirmation = StudentInformationConfirmation::query()->create([
                    'student_id' => $student->id, 'sequence' => ($context->latest->sequence ?? 0) + 1, 'user_id' => $user->id,
                    'student_record_id' => $record->id, 'academic_year_id' => $record->academic_year_id,
                    'information_fingerprint' => $fingerprint, 'confirmed_at' => now(),
                ]);
                activity('student_confirmations')->causedBy($user)->performedOn($confirmation)->event('student_information_confirmed')
                    ->withProperties(['user_id' => $user->id, 'student_id' => $student->id, 'student_record_id' => $record->id, 'academic_year_id' => $record->academic_year_id, 'confirmation_id' => $confirmation->id])
                    ->log('student_information_confirmed');
                StaffNotifications::student($student, StudentActivity::INFORMATION_CONFIRMED, trim($student->last_name.' '.$student->first_names).' a confirmé ses informations ('.$record->academicYear->name.').', $student->uuid, 'students.view');

                return StudentConfirmationContext::load($student->setRelation('studentRecords', collect([$record])));
            }, 5);
        } catch (UniqueConstraintViolationException $race) {
            // A concurrent confirmation took the same sequence: replay once, it now sees the winner.
            if (! $retry) {
                throw $race;
            }

            return $this->handle($actor, $version, false);
        }
    }
}
