<?php

namespace App\Actions\Corrections;

use App\Actions\Students\SaveStudentIdentity;
use App\Enums\StudentCorrectionStatus;
use App\Http\Requests\Students\CreateStudentRequest;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\User;
use App\Support\StudentCorrectionAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReviewStudentCorrection
{
    public function handle(User $actor, StudentCorrectionRequest $correction, bool $approve, ?string $reason): void
    {
        Gate::forUser($actor)->authorize('review', $correction);
        if (! $approve && blank($reason)) {
            throw ValidationException::withMessages(['rejection_reason' => 'Un motif de refus est obligatoire.']);
        }
        // Same short lock as manual identity edits, then a single transaction for the whole decision.
        Cache::lock('students:manual-identity-write', 60)->block(10, fn () => DB::transaction(fn () => $this->decide($actor, $correction->id, $approve, $reason), 5));
    }

    private function decide(User $actor, int $id, bool $approve, ?string $reason): void
    {
        $target = StudentCorrectionRequest::query()->lockForUpdate()->findOrFail($id);
        if ($approve && $target->status === StudentCorrectionStatus::Approved) {
            return;
        }
        if ($target->status !== StudentCorrectionStatus::Pending) {
            $this->fail('Cette demande a déjà été traitée ou annulée. Actualisez la page.');
        }
        $student = Student::query()->lockForUpdate()->findOrFail($target->student_id);
        $target->setRelation('student', $student);
        Gate::forUser($actor)->authorize('review', $target);
        if ($approve) {
            if (! $student->is_active) {
                $this->fail('La fiche étudiante est inactive : la correction ne peut pas être appliquée.');
            }
            // Optimistic consistency: never overwrite a value changed since the request was made.
            if (! hash_equals($target->current_fingerprint, $target->field->fingerprint($student))) {
                $this->fail('La valeur officielle a changé depuis la demande. Réexaminez la fiche puis refusez cette demande si elle n’est plus pertinente.');
            }
            $validator = Validator::make(['value' => $target->proposed_value], ['value' => $target->field->rules()], (new CreateStudentRequest)->messages());
            if ($validator->fails()) {
                $this->fail('La valeur proposée ne respecte plus les règles actuelles : refusez la demande.');
            }
            // Reuses manual-entry protections: authorization, identity locks, duplicate CIN/BAC/identity checks.
            app(SaveStudentIdentity::class)->persist($actor, $student, [$target->field->value => $target->proposed_value]);
        }
        $target->update([
            'status' => $approve ? StudentCorrectionStatus::Approved : StudentCorrectionStatus::Rejected,
            'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'rejection_reason' => $approve ? null : $reason,
        ]);
        StudentCorrectionAudit::log($actor, $target, $approve ? 'correction_approved' : 'correction_rejected');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['correction' => $message]);
    }
}
