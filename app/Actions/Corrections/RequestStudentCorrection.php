<?php

namespace App\Actions\Corrections;

use App\Actions\Students\SaveStudentIdentity;
use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use App\Http\Requests\Students\CreateStudentRequest;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\User;
use App\Notifications\StudentActivity;
use App\Support\StaffNotifications;
use App\Support\StudentCorrectionAudit;
use App\Support\StudentNormalization;
use App\Support\StudentPortalAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestStudentCorrection
{
    public function handle(User $actor, StudentCorrectionField $field, mixed $value, ?string $message): StudentCorrectionRequest
    {
        $value = StudentNormalization::value($field->value, $value);
        $rules = new CreateStudentRequest;
        Validator::make(['value' => $value], ['value' => $field->rules()], $rules->messages(), ['value' => mb_strtolower($field->label())])->validate();
        $proposed = (string) $value;
        if ($field->appliesDirectly()) {
            return $this->applyDirectly($actor, $field, $proposed, $message);
        }

        try {
            return DB::transaction(function () use ($actor, $field, $proposed, $message) {
                $user = User::query()->lockForUpdate()->findOrFail($actor->id);
                abort_unless(StudentPortalAccess::student($user) && $user->hasVerifiedEmail() && $user->can('corrections.create'), 403);
                // The student always comes from the authenticated account, never from the payload.
                $student = Student::query()->whereKey($user->student_id)->where('is_active', true)->lockForUpdate()->first();
                abort_if($student === null, 403, 'Le compte ne permet pas actuellement de demander une correction.');
                if ($field->valueOf($student) === $proposed) {
                    throw ValidationException::withMessages(['value' => 'La valeur proposée est identique à la valeur officielle.']);
                }
                if (StudentCorrectionRequest::query()->where('student_id', $student->id)->where('field', $field->value)->where('status', StudentCorrectionStatus::Pending->value)->exists()) {
                    $this->alreadyPending();
                }
                $correction = StudentCorrectionRequest::query()->create([
                    'student_id' => $student->id, 'user_id' => $user->id, 'field' => $field, 'proposed_value' => $proposed,
                    'current_fingerprint' => $field->fingerprint($student), 'status' => StudentCorrectionStatus::Pending, 'student_message' => $message,
                ]);
                StudentCorrectionAudit::log($user, $correction, 'correction_requested');
                StaffNotifications::student($student, StudentActivity::CORRECTION_REQUESTED, trim($student->last_name.' '.$student->first_names).' signale une erreur : '.mb_strtolower($field->label()).'.', $correction->uuid, 'corrections.view');

                return $correction;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            $this->alreadyPending();
        }
    }

    /**
     * Phone or address: saved at once on the official record (ADR-089), with the same duplicate checks as
     * manual entry, and kept in the student's requests as an applied change. Same lock order as a staff review.
     */
    private function applyDirectly(User $actor, StudentCorrectionField $field, string $proposed, ?string $message): StudentCorrectionRequest
    {
        return Cache::lock('students:manual-identity-write', 60)->block(10, fn () => DB::transaction(function () use ($actor, $field, $proposed, $message) {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(StudentPortalAccess::student($user) && $user->hasVerifiedEmail() && $user->can('corrections.create'), 403);
            $student = Student::query()->whereKey($user->student_id)->where('is_active', true)->lockForUpdate()->first();
            abort_if($student === null, 403, 'Le compte ne permet pas actuellement de modifier ces informations.');
            if ($field->valueOf($student) === $proposed) {
                throw ValidationException::withMessages(['value' => 'La valeur proposée est identique à la valeur officielle.']);
            }
            $fingerprint = $field->fingerprint($student);
            try {
                app(SaveStudentIdentity::class)->persist($user, $student, [$field->value => $proposed], 'updateOwnContact');
            } catch (ValidationException $exception) {
                // Duplicate phone: the message speaks of the value, under the field the portal shows.
                throw ValidationException::withMessages(['value' => collect($exception->errors())->flatten()->first()]);
            }
            // A pending request on the same field is overtaken by the change.
            StudentCorrectionRequest::query()->where('student_id', $student->id)->where('field', $field->value)->where('status', StudentCorrectionStatus::Pending->value)
                ->update(['status' => StudentCorrectionStatus::Cancelled->value, 'updated_at' => now()]);
            $correction = StudentCorrectionRequest::query()->create([
                'student_id' => $student->id, 'user_id' => $user->id, 'field' => $field, 'proposed_value' => $proposed, 'current_fingerprint' => $fingerprint,
                'status' => StudentCorrectionStatus::Approved, 'student_message' => $message, 'reviewed_at' => now(),
            ]);
            StudentCorrectionAudit::log($user, $correction, 'correction_applied');
            // Saved without validation: the staff still hears of it (nationality changes the eligibility).
            StaffNotifications::student($student, StudentActivity::INFORMATION_UPDATED, trim($student->last_name.' '.$student->first_names).' a modifié : '.mb_strtolower($field->label()).'.', $student->uuid, 'students.view');

            return $correction;
        }, 5));
    }

    private function alreadyPending(): never
    {
        throw ValidationException::withMessages(['field' => 'Une demande est déjà en attente pour cette information. Annulez-la avant d’en créer une nouvelle.']);
    }
}
