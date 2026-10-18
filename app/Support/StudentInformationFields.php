<?php

namespace App\Support;

use App\Enums\StudentCorrectionField;
use App\Models\Student;
use App\Models\StudentInformationRequest;

/**
 * Fields an agent can point out to a student by email: the ones the student can correct from the portal
 * (StudentCorrectionField). Empty ones are « missing »; a few recognisable anomalies are « to check ».
 */
final class StudentInformationFields
{
    /** @return list<string> */
    public static function missing(Student $student): array
    {
        return array_values(array_map(fn (StudentCorrectionField $field) => $field->value, array_filter(
            StudentCorrectionField::cases(),
            fn (StudentCorrectionField $field) => $field->valueOf($student) === null,
        )));
    }

    /** @return list<string> values that are present but look wrong */
    public static function doubtful(Student $student): array
    {
        return array_values(array_filter([
            filled($student->cin) && ! $student->hasStandardCin() ? StudentCorrectionField::Cin->value : null,
            filled($student->phone) && preg_match('/^0\d{9}$/', (string) $student->phone) !== 1 ? StudentCorrectionField::Phone->value : null,
        ]));
    }

    /**
     * Fields suggested for a sending: missing ones, then those to check.
     *
     * @return list<string>
     */
    public static function suggested(Student $student): array
    {
        return array_values(array_unique([...self::missing($student), ...self::doubtful($student)]));
    }

    /**
     * What the agent's dialog needs: every field with its state, whether the student can be reached,
     * and the last sending. Never a value.
     *
     * @return array<string,mixed>
     */
    public static function forStaff(Student $student): array
    {
        $missing = self::missing($student);
        $doubtful = self::doubtful($student);
        $last = StudentInformationRequest::query()->where('student_id', $student->id)->latest('id')->with('sender:id,name')->first();

        return [
            'has_email' => filled($student->email),
            'fields' => array_map(fn (StudentCorrectionField $field) => [
                'value' => $field->value,
                'label' => $field->label(),
                'state' => in_array($field->value, $missing, true) ? 'missing' : (in_array($field->value, $doubtful, true) ? 'doubtful' : null),
            ], array_values(array_filter(StudentCorrectionField::cases(), fn ($field) => $field !== StudentCorrectionField::Email))),
            'last' => $last === null ? null : ['at' => $last->created_at->toIso8601String(), 'by' => $last->sender?->name, 'count' => count($last->fields)],
        ];
    }
}
