<?php

namespace App\Actions\Scholarships;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\ScholarshipCheckStatus;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationStatusHistory;
use App\Models\User;
use App\Support\ScholarshipAudit;
use App\Support\Scholarships\ValidationBlockers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransitionScholarshipApplication
{
    /**
     * $confirmPendingChecks: quick validation from the list. The checks still to do are recorded as
     * conforming by the actor (each one audited, the file goes through « En vérification »), then the
     * file is validated; a non-conforming check or an unconfirmed situation still refuses it, and
     * nothing is changed then (one transaction).
     */
    public function handle(User $actor, ScholarshipApplication $application, ScholarshipApplicationStatus $target, ?string $reason = null, bool $confirmPendingChecks = false): ScholarshipApplication
    {
        Gate::forUser($actor)->authorize('transition', [$application, $target]);
        if ($confirmPendingChecks && $target === ScholarshipApplicationStatus::Validated) {
            return DB::transaction(function () use ($actor, $application, $target) {
                $fresh = ScholarshipApplication::query()->with(['student', 'studentRecord.establishment:id,name', 'checks'])->lockForUpdate()->findOrFail($application->id);
                if ($fresh->status === $target) {
                    return $fresh;
                }
                $reasons = ValidationBlockers::for($fresh, true);
                if ($reasons !== []) {
                    $this->fail('Validation impossible. '.implode(' ', $reasons));
                }
                foreach ($fresh->checks->where('status', ScholarshipCheckStatus::Pending) as $check) {
                    app(UpdateScholarshipCheck::class)->handle($actor, $fresh, $check->key, ScholarshipCheckStatus::Ok, null);
                }

                return $this->handle($actor, $fresh, $target);
            }, 5);
        }
        $reason = filled($reason) ? trim((string) $reason) : null;
        if ($target->requiresReason() && (blank($reason) || mb_strlen((string) $reason) < 10)) {
            throw ValidationException::withMessages(['reason' => 'Un motif d’au moins dix caractères est obligatoire.']);
        }

        return DB::transaction(function () use ($actor, $application, $target, $reason) {
            $locked = ScholarshipApplication::query()->with(['student', 'studentRecord', 'checks'])->lockForUpdate()->findOrFail($application->id);
            // Repeated click or concurrent identical decision: no second decision, no second history line.
            if ($locked->status === $target) {
                return $locked;
            }
            $from = $locked->status;
            if (! $from->canTransitionTo($target)) {
                $this->fail($from->isFinal() ? 'Ce dossier a déjà reçu une décision définitive ('.$from->label().').' : 'Transition impossible : '.$from->label().' → '.$target->label().'.');
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
        $application->loadMissing('studentRecord.establishment:id,name');
        $reasons = ValidationBlockers::for($application);
        if ($reasons !== []) {
            $this->fail('Validation impossible. '.implode(' ', $reasons));
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
