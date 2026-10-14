<?php

namespace App\Support;

use App\Models\ScholarshipApplication;
use App\Models\ScholarshipCampaign;
use App\Models\User;

/** Identifiers, statuses and checklist keys only: never personal data, reasons or notes. */
class ScholarshipAudit
{
    /** @param array<string,mixed> $extra */
    public static function campaign(User $actor, ScholarshipCampaign $campaign, string $event, array $extra = []): void
    {
        activity('scholarship_campaigns')->causedBy($actor)->performedOn($campaign)->event($event)
            ->withProperties(['campaign_id' => $campaign->id, 'status' => $campaign->status->value, ...$extra])->log($event);
    }

    /** @param array<string,mixed> $extra */
    public static function application(User $actor, ScholarshipApplication $application, string $event, array $extra = []): void
    {
        activity('scholarships')->causedBy($actor)->performedOn($application)->event($event)
            ->withProperties(['application_id' => $application->id, 'campaign_id' => $application->scholarship_campaign_id, 'student_id' => $application->student_id, 'status' => $application->status->value, ...$extra])->log($event);
    }
}
