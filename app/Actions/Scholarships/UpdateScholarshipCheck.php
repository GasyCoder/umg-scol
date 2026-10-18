<?php

namespace App\Actions\Scholarships;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\ScholarshipCheckStatus;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationStatusHistory;
use App\Models\User;
use App\Support\ScholarshipAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateScholarshipCheck
{
    /** Recording a check on a freshly received file starts its review. */
    public function handle(User $actor, ScholarshipApplication $application, string $key, ScholarshipCheckStatus $status, ?string $note): void
    {
        Gate::forUser($actor)->authorize('review', $application);
        DB::transaction(function () use ($actor, $application, $key, $status, $note) {
            $locked = ScholarshipApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ($locked->status->isFinal()) {
                throw ValidationException::withMessages(['check' => 'Ce dossier n’est plus modifiable.']);
            }
            $check = $locked->checks()->where('key', $key)->lockForUpdate()->first();
            if ($check === null) {
                throw ValidationException::withMessages(['check' => 'Contrôle inconnu pour ce dossier.']);
            }
            if ($locked->status === ScholarshipApplicationStatus::Pending) {
                $locked->update(['status' => ScholarshipApplicationStatus::InReview, 'review_started_by' => $actor->id, 'review_started_at' => now()]);
                ScholarshipApplicationStatusHistory::query()->create(['scholarship_application_id' => $locked->id, 'from_status' => ScholarshipApplicationStatus::Pending, 'to_status' => ScholarshipApplicationStatus::InReview, 'actor_id' => $actor->id]);
                ScholarshipAudit::application($actor, $locked, 'review_started', ['from_status' => 'pending']);
            }
            $check->update(['status' => $status, 'note' => filled($note) ? $note : null, 'checked_by' => $status === ScholarshipCheckStatus::Pending ? null : $actor->id, 'checked_at' => $status === ScholarshipCheckStatus::Pending ? null : now()]);
            ScholarshipAudit::application($actor, $locked, 'check_updated', ['check_key' => $key, 'check_status' => $status->value]);
        }, 5);
    }
}
