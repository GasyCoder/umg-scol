<?php

namespace App\Support\Scholarships;

use App\Enums\ScholarshipCheckStatus;
use App\Enums\SituationVerification;
use App\Enums\StudentRecordStatus;
use App\Models\ScholarshipApplication;

/**
 * Why a paper file cannot be validated yet, in words an agent can act on. Empty when it can.
 * Shared by the decision itself (refusal message) and the screens (« Valider » greyed with the reason).
 */
final class ValidationBlockers
{
    /**
     * Expects student, studentRecord.establishment and checks loaded (or the checks_count,
     * checks_pending and checks_issue counts). $confirmingPending: the quick validation of the list,
     * which records the checks still to do as conforming; only a non-conforming check blocks then.
     *
     * @return list<string>
     */
    public static function for(ScholarshipApplication $application, bool $confirmingPending = false): array
    {
        $record = $application->studentRecord;
        $establishment = $record->establishment->name ?? 'l’établissement';
        $reasons = [];
        if (! $application->student->is_active) {
            $reasons[] = 'La fiche de l’étudiant est désactivée.';
        }
        if ($record->student_id !== $application->student_id || $record->academic_year_id !== $application->academic_year_id) {
            $reasons[] = 'La situation rattachée au dossier n’est pas celle de son année universitaire.';
        } elseif ($record->status === StudentRecordStatus::Inactive) {
            $reasons[] = 'La situation de l’année de l’étudiant est désactivée.';
        } elseif ($record->verification === SituationVerification::Matched) {
            $reasons[] = 'L’étudiant a été retrouvé dans la liste de '.$establishment.' : validez d’abord sa situation (fiche de l’étudiant › Situations).';
        } elseif ($record->verification !== SituationVerification::Verified || $record->status !== StudentRecordStatus::Active) {
            $reasons[] = 'La situation de l’étudiant est en attente de la liste de '.$establishment.' : le dossier pourra être validé une fois cette liste importée.';
        }
        [$pending, $issues, $total] = self::checks($application);
        $open = $issues + ($confirmingPending ? 0 : $pending);
        if ($total === 0) {
            $reasons[] = 'Le dossier n’a aucun contrôle.';
        } elseif ($confirmingPending && $issues > 0) {
            $reasons[] = $issues.' contrôle'.($issues > 1 ? 's' : '').' non conforme'.($issues > 1 ? 's' : '').' : ouvrez le dossier pour '.($issues > 1 ? 'les' : 'le').' revoir.';
        } elseif ($open > 0) {
            $reasons[] = $open.' contrôle'.($open > 1 ? 's' : '').' sur '.$total.' '.($open > 1 ? 'restent' : 'reste').' à faire ou non conforme'.($open > 1 ? 's' : '').' : ouvrez le dossier pour les compléter.';
        }

        return $reasons;
    }

    /** @return array{0:int,1:int,2:int} checks still to do, non-conforming checks, checks in all */
    private static function checks(ScholarshipApplication $application): array
    {
        if ($application->relationLoaded('checks')) {
            return [
                $application->checks->where('status', ScholarshipCheckStatus::Pending)->count(),
                $application->checks->where('status', ScholarshipCheckStatus::Issue)->count(),
                $application->checks->count(),
            ];
        }

        return [(int) $application->getAttribute('checks_pending'), (int) $application->getAttribute('checks_issue'), (int) $application->getAttribute('checks_count')];
    }
}
