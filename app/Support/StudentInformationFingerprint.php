<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentRecord;
use Carbon\CarbonInterface;

/**
 * Keyed fingerprint of exactly what the student portal shows and the student confirms:
 * the visible identity, contact and BAC fields, plus the current annual declaration with the
 * identifiers and displayed names of its year, establishment, level and program.
 * CIN data is never shown to the student, so it is not part of the confirmed version.
 *
 * Same convention as correction fingerprints: HMAC-SHA256 keyed with APP_KEY (no secret in the
 * database). Rotating APP_KEY makes every confirmation stale: fail-safe, the student confirms again.
 */
final class StudentInformationFingerprint
{
    public const VERSION = 'v1';

    public const STUDENT_FIELDS = [
        'student_number', 'last_name', 'first_names', 'birth_date', 'birth_place',
        'phone', 'email', 'address',
        'bac_registration_number', 'bac_series', 'bac_year', 'bac_mention',
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
            'program.id' => $record->program_id, 'program.name' => $record->program?->name,
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
        $string = $value instanceof CarbonInterface ? $value->format('Y-m-d') : (string) $value;

        return strlen($string).':'.$string;
    }
}
