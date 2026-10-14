<?php

namespace App\Actions\Scholarships;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\ScholarshipCampaignStatus;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationStatusHistory;
use App\Models\User;
use App\Support\ScholarshipAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransitionScholarshipApplication
{
    public function handle(User $actor, ScholarshipApplication $application, ScholarshipApplicationStatus $target, ?string $reason = null): ScholarshipApplication
    {
        Gate::forUser($actor)->authorize('transition', [$application, $target]);
        $reason = filled($reason) ? trim((string) $reason) : null;
        if ($target->requiresReason() && (blank($reason) || mb_strlen((string) $reason) < 10)) {
            throw ValidationException::withMessages(['reason' => 'Un motif d’au moins dix caractères est obligatoire.']);
        }

        return DB::transaction(function () use ($actor, $application, $target, $reason) {
            $locked = ScholarshipApplication::query()->with(['campaign', 'student', 'studentRecord', 'checks'])->lockForUpdate()->findOrFail($application->id);
            // Repeated click or concurrent identical decision: no second decision, no second history line.
            if ($locked->status === $target) {
                return $locked;
            }
            $from = $locked->status;
            if (! $from->canTransitionTo($target)) {
                $this->fail($from->isFinal() ? 'Ce dossier a déjà reçu une décision définitive ('.$from->label().').' : 'Transition impossible : '.$from->label().' → '.$target->label().'.');
            }
            if ($locked->campaign->status === ScholarshipCampaignStatus::Archived) {
                $this->fail('La campagne est archivée : le dossier ne peut plus être traité.');
            }
            if ($target === ScholarshipApplicationStatus::Validated) {
                $this->ensureValidatable($locked);
            }
            $changes = ['status' => $target];
            if ($target === ScholarshipApplicationStatus::InReview) {
                $changes += ['review_started_by' => $actor->id, 'review_started_at' => now(), 'decision_reason' => null];
            } elseif ($target === ScholarshipApplicationStatus::NeedsCorrection) {
                $changes += ['decision_reason' => $reason];
            } else {
                $changes += ['decided_by' => $actor->id, 'decided_at' => now(), 'decision_reason' => $target === ScholarshipApplicationStatus::Rejected ? $reason : null];
            }
            $locked->update($changes);
            ScholarshipApplicationStatusHistory::query()->create(['scholarship_application_id' => $locked->id, 'from_status' => $from, 'to_status' => $target, 'actor_id' => $actor->id, 'reason' => $target->requiresReason() ? $reason : null]);
            ScholarshipAudit::application($actor, $locked, match ($target) {
                ScholarshipApplicationStatus::InReview => 'review_started',
                ScholarshipApplicationStatus::Validated => 'application_validated',
                ScholarshipApplicationStatus::NeedsCorrection => 'application_needs_correction',
                ScholarshipApplicationStatus::Rejected => 'application_rejected',
                ScholarshipApplicationStatus::Pending => 'application_received',
            }, ['from_status' => $from->value]);

            return $locked;
        }, 5);
    }

    private function ensureValidatable(ScholarshipApplication $application): void
    {
        $record = $application->studentRecord;
        if (! $application->student->is_active || $record->student_id !== $application->student_id || $record->academic_year_id !== $application->campaign->academic_year_id || $record->status->value !== 'active') {
            $this->fail('La fiche étudiante ou la déclaration annuelle n’est plus active ou ne correspond plus à la campagne.');
        }
        $incomplete = $application->checks->reject(fn ($check) => $check->status->allowsValidation());
        if ($application->checks->isEmpty() || $incomplete->isNotEmpty()) {
            $this->fail('Tous les contrôles doivent être conformes ou non applicables avant validation.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
