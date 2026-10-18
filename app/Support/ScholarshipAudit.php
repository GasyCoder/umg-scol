<?php

namespace App\Support;

use App\Models\ScholarshipApplication;
use App\Models\User;

/** Identifiers, statuses and checklist keys only: never personal data, reasons or notes. */
class ScholarshipAudit
{
    /** @param array<string,mixed> $extra */
    public static function application(User $actor, ScholarshipApplication $application, string $event, array $extra = []): void
    {
        activity('scholarships')->causedBy($actor)->performedOn($application)->event($event)
            ->withProperties(['application_id' => $application->id, 'academic_year_id' => $application->academic_year_id, 'student_id' => $application->student_id, 'status' => $application->status->value, ...$extra])->log($event);
    }
}
