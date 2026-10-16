<?php

namespace App\Support;

use App\Enums\RepeatCode;
use App\Models\Student;
use App\Models\StudentRecord;

/**
 * Rule of the establishment file (columns BOURSIER and TAUX DE BOURSE), applied by the server
 * instead of trusting spreadsheet formulas: Malagasy nationality, BAC obtained no later than the
 * limit of the academic year, a scholarship request reference, and a new or passing (rate 1) or repeating
 * (rate 1/4) student. A triple-or-more student gets no scholarship.
 *
 * Indicative only: the paper file is still checked and decided by the central scholarship office.
 */
final class ScholarshipEligibility
{
    public const ELIGIBLE = 'eligible';

    public const NOT_ELIGIBLE = 'not_eligible';

    /** The academic year has no BAC year limit yet. */
    public const UNDETERMINED = 'undetermined';

    /**
     * The record must have its academicYear loaded.
     *
     * @return array{status: string, rate: string, reasons: list<string>}
     */
    public static function evaluate(Student $student, StudentRecord $record): array
    {
        $reasons = [];
        if ($student->nationality !== 'Malagasy') {
            $reasons[] = 'Nationalité non malagasy ou non renseignée.';
        }
        if (blank($record->scholarship_request_reference)) {
            $reasons[] = 'Aucune référence de demande de bourse.';
        }
        if (! in_array($record->repeat_code, [RepeatCode::New, RepeatCode::Passing, RepeatCode::Repeating], true)) {
            $reasons[] = $record->repeat_code === null ? 'Code de redoublement non renseigné.' : 'Triplant ou plus : pas de bourse.';
        }
        $limit = $record->academicYear->scholarship_max_bac_year;
        if ($student->bac_year === null) {
            $reasons[] = 'Année du BAC non renseignée.';
        } elseif ($limit !== null && $student->bac_year > $limit) {
            $reasons[] = 'BAC obtenu après '.$limit.'.';
        }
        $status = match (true) {
            $reasons !== [] => self::NOT_ELIGIBLE,
            $limit === null => self::UNDETERMINED,
            default => self::ELIGIBLE,
        };
        if ($status === self::UNDETERMINED) {
            $reasons[] = 'Année limite du BAC non définie pour cette année universitaire.';
        }

        return ['status' => $status, 'rate' => $status !== self::ELIGIBLE ? '0' : match ($record->repeat_code) {
            RepeatCode::New, RepeatCode::Passing => '1',
            RepeatCode::Repeating => '1/4',
            default => '0',
        }, 'reasons' => $reasons];
    }
}
