<?php

namespace App\Actions\Scholarships;

use App\Enums\ScholarshipCampaignStatus;
use App\Models\ScholarshipCampaign;
use App\Models\User;
use App\Support\ScholarshipAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransitionScholarshipCampaign
{
    public function handle(User $actor, ScholarshipCampaign $campaign, ScholarshipCampaignStatus $target): ScholarshipCampaign
    {
        Gate::forUser($actor)->authorize('manage', ScholarshipCampaign::class);

        // The campaign row lock also serializes this change with paper file receptions.
        return DB::transaction(function () use ($actor, $campaign, $target) {
            $locked = ScholarshipCampaign::query()->with('academicYear')->lockForUpdate()->findOrFail($campaign->id);
            if ($locked->status === $target) {
                return $locked;
            }
            $from = $locked->status;
            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'Transition impossible : '.$from->label().' → '.$target->label().'.']);
            }
            if ($target === ScholarshipCampaignStatus::Open && ! $locked->academicYear->is_active) {
                throw ValidationException::withMessages(['status' => 'L’année universitaire de la campagne est inactive.']);
            }
            $locked->update(['status' => $target]);
            ScholarshipAudit::campaign($actor, $locked, 'campaign_status_changed', ['from_status' => $from->value]);

            return $locked;
        }, 5);
    }
}
