<?php

namespace App\Actions\Students;

use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentInformationFields;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The same email for a selection of students (or of scholarship files): each student receives the fields
 * suggested for their own record. Students without email, inactive, with nothing to point out or outside
 * the account's rights are skipped and counted; one never blocks the others.
 */
class NotifyStudentsInformation
{
    public function __construct(private NotifyStudentInformation $notify) {}

    /**
     * @param  'students'|'records'|'applications'  $kind  what the uuids designate
     * @param  list<string>  $uuids
     * @return array{sent:int,no_email:int,nothing:int,refused:int}
     */
    public function handle(User $actor, string $kind, array $uuids): array
    {
        $result = ['sent' => 0, 'no_email' => 0, 'nothing' => 0, 'refused' => 0];
        $targets = match ($kind) {
            'applications' => ScholarshipApplication::query()->whereIn('uuid', $uuids)->with('student')->get()->map(fn ($application) => [$application->student, $application]),
            'records' => StudentRecord::query()->whereIn('uuid', $uuids)->with('student')->get()->map(fn ($record) => [$record->student, null]),
            default => Student::query()->whereIn('uuid', $uuids)->get()->map(fn ($student) => [$student, null]),
        };
        $result['refused'] += count($uuids) - $targets->count();
        $seen = [];
        foreach ($targets as [$student, $application]) {
            // Two files of one student: a single email.
            if ($student === null || isset($seen[$student->id])) {
                continue;
            }
            $seen[$student->id] = true;
            $fields = StudentInformationFields::suggested($student);
            if (blank($student->email)) {
                $result['no_email']++;
            } elseif ($fields === []) {
                $result['nothing']++;
            } else {
                try {
                    $this->notify->handle($actor, $student, $fields, null, $application);
                    $result['sent']++;
                } catch (AuthorizationException|ValidationException) {
                    $result['refused']++;
                }
            }
        }

        return $result;
    }
}
