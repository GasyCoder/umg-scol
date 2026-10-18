<?php

namespace App\Actions\Students;

use App\Enums\StudentCorrectionField;
use App\Mail\StudentInformationRequestMail;
use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\StudentInformationRequest;
use App\Models\User;
use App\Support\StudentInformationFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Emails a student the fields of their record to complete or check (ADR-088), records the sending
 * and audits it with field names only. The official record is never changed here.
 */
class NotifyStudentInformation
{
    /** @param list<string> $fields StudentCorrectionField values */
    public function handle(User $actor, Student $student, array $fields, ?string $message = null, ?ScholarshipApplication $application = null): StudentInformationRequest
    {
        Gate::forUser($actor)->authorize('notify', $student);
        $fields = array_values(array_intersect(array_column(StudentCorrectionField::cases(), 'value'), $fields));
        $message = filled($message) ? trim((string) $message) : null;
        if (blank($student->email)) {
            throw ValidationException::withMessages(['fields' => 'Aucun email sur la fiche : l’étudiant ne peut pas être prévenu.']);
        }
        if (! $student->is_active) {
            throw ValidationException::withMessages(['fields' => 'Fiche inactive : l’étudiant ne peut pas être prévenu.']);
        }
        if ($fields === [] && $message === null) {
            throw ValidationException::withMessages(['fields' => 'Choisissez au moins une information ou écrivez un message.']);
        }

        return DB::transaction(function () use ($actor, $student, $fields, $message, $application): StudentInformationRequest {
            $request = StudentInformationRequest::query()->create([
                'student_id' => $student->id, 'sent_by' => $actor->id, 'scholarship_application_id' => $application?->id, 'fields' => $fields, 'message' => $message,
            ]);
            $missing = StudentInformationFields::missing($student);
            Mail::to($student->email)->queue(new StudentInformationRequestMail(
                trim($student->last_name.' '.$student->first_names),
                array_map(fn (string $field) => ['label' => StudentCorrectionField::from($field)->label(), 'missing' => in_array($field, $missing, true)], $fields),
                $message,
            ));
            // Not performedOn($student): its activity hook would replace these properties with changed fields.
            activity('students')->causedBy($actor)->event('student_information_requested')
                ->withProperties(array_filter(['student_id' => $student->id, 'fields' => $fields, 'with_message' => $message !== null, 'application_id' => $application?->id], fn ($value) => $value !== null))
                ->log('student_information_requested');

            return $request;
        });
    }
}
