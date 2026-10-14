<?php

namespace App\Actions\Scholarships;

use App\Enums\ScholarshipCampaignStatus;
use App\Models\ScholarshipCampaign;
use App\Models\User;
use App\Support\ScholarshipAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveScholarshipCampaign
{
    /** @param array<string,mixed> $data validated campaign attributes */
    public function handle(User $actor, ?ScholarshipCampaign $campaign, array $data): ScholarshipCampaign
    {
        Gate::forUser($actor)->authorize('manage', ScholarshipCampaign::class);
        try {
            return DB::transaction(function () use ($actor, $campaign, $data) {
                $target = $campaign === null ? new ScholarshipCampaign(['status' => ScholarshipCampaignStatus::Draft, 'created_by' => $actor->id])
                    : ScholarshipCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
                if ($target->status === ScholarshipCampaignStatus::Archived) {
                    throw ValidationException::withMessages(['campaign' => 'Une campagne archivée ne peut plus être modifiée.']);
                }
                if ($target->exists && $target->academic_year_id !== (int) $data['academic_year_id'] && ($target->status !== ScholarshipCampaignStatus::Draft || $target->applications()->exists())) {
                    throw ValidationException::withMessages(['academic_year_id' => 'L’année universitaire ne peut changer que pour une campagne en brouillon sans dossier.']);
                }
                $target->fill($data);
                if ($target->starts_at !== null && $target->ends_at !== null && $target->starts_at->greaterThan($target->ends_at)) {
                    throw ValidationException::withMessages(['ends_at' => 'La date de fin doit être égale ou postérieure à la date de début.']);
                }
                $created = ! $target->exists;
                $changed = array_keys($target->getDirty());
                $target->save();
                if ($created || $changed !== []) {
                    ScholarshipAudit::campaign($actor, $target, $created ? 'campaign_created' : 'campaign_updated', ['changed_fields' => array_values(array_diff($changed, ['created_by', 'status']))]);
                }

                return $target;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Une campagne de ce nom existe déjà pour cette année universitaire.']);
        }
    }
}
