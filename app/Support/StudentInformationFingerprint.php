<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentRecord;
use Carbon\CarbonInterface;

/**
 * Keyed fingerprint of exactly what the student portal shows and the student confirms:
 * the visible identity, contact and BAC fields, plus the current annual declaration with the
 * identifiers and displayed names of its year, establishment, level, mention and program, and its repeat code.
 * CIN data is never shown to the student, so it is not part of the confirmed version.
 *
 * Same convention as correction fingerprints: HMAC-SHA256 keyed with APP_KEY (no secret in the
 * database). Rotating APP_KEY makes every confirmation stale: fail-safe, the student confirms again.
 */
final class StudentInformationFingerprint
{
    /** v2: fields of the establishment file (sex, mother, nationality, repeat code, mention). */
    public const VERSION = 'v2';

    public const STUDENT_FIELDS = [
        'last_name', 'first_names', 'sex', 'birth_date', 'mother_name', 'nationality',
        'phone', 'email', 'address', 'bac_series', 'bac_year',
    ];

    /** The record must have academicYear, establishment, level and program loaded. */
    public static function compute(Student $student, StudentRecord $record): string
    {
        $values = [];
        foreach (self::STUDENT_FIELDS as $field) {
            $values['student.'.$field] = $student->getAttribute($field);
        }
        $values += [
            'record.id' => $record->id, 'record.status' => $record->status->value,
            'year.id' => $record->academic_year_id, 'year.name' => $record->academicYear->name,
            'establishment.id' => $record->establishment_id, 'establishment.name' => $record->establishment->name,
            'level.id' => $record->level_id, 'level.name' => $record->level->name,
            'mention.name' => $record->program?->mention?->name,
            'program.id' => $record->program_id, 'program.name' => $record->program?->name,
            'record.repeat_code' => $record->repeat_code?->value,
        ];
        // Fixed key order and length-prefixed values: canonical without relying on JSON encoding.
        $lines = array_map(fn (string $key, mixed $value) => $key.'='.self::encode($value), array_keys($values), $values);

        return hash_hmac('sha256', 'student-information-confirmation:'.self::VERSION."\n".implode("\n", $lines), (string) config('app.key'));
    }

    /**
     * Opaque token of the version displayed to the student, sent back on confirmation so that a
     * version changed after display is never confirmed. Derived from, but distinct from, the stored fingerprint.
     */
    public static function viewToken(string $fingerprint): string
    {
        return hash_hmac('sha256', 'student-information-confirmation-view:'.$fingerprint, (string) config('app.key'));
    }

    private static function encode(mixed $value): string
    {
        if ($value === null) {
            return '~';
        }
        $string = match (true) {
            $value instanceof CarbonInterface => $value->format('Y-m-d'),
            $value instanceof \BackedEnum => (string) $value->value,
            default => (string) $value,
        };

        return strlen($string).':'.$string;
    }
}
