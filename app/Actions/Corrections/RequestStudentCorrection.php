<?php

namespace App\Actions\Corrections;

use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use App\Http\Requests\Students\CreateStudentRequest;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\User;
use App\Support\StudentCorrectionAudit;
use App\Support\StudentNormalization;
use App\Support\StudentPortalAccess;
use Illuminate\Database\UniqueConstraintViolationException;
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

                return $correction;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            $this->alreadyPending();
        }
    }

    private function alreadyPending(): never
    {
        throw ValidationException::withMessages(['field' => 'Une demande est déjà en attente pour cette information. Annulez-la avant d’en créer une nouvelle.']);
    }
}
