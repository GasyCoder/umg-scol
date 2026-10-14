<?php

namespace App\Actions\Corrections;

use App\Enums\StudentCorrectionStatus;
use App\Models\StudentCorrectionRequest;
use App\Models\User;
use App\Support\StudentCorrectionAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelStudentCorrection
{
    public function handle(User $actor, StudentCorrectionRequest $correction): StudentCorrectionRequest
    {
        return DB::transaction(function () use ($actor, $correction) {
            $target = StudentCorrectionRequest::query()->lockForUpdate()->findOrFail($correction->id);
            abort_unless($target->user_id === $actor->id && $target->student_id === $actor->student_id, 404);
            if ($target->status === StudentCorrectionStatus::Cancelled) {
                return $target;
            }
            if ($target->status !== StudentCorrectionStatus::Pending) {
                throw ValidationException::withMessages(['correction' => 'Cette demande a déjà été traitée et ne peut plus être annulée.']);
            }
            $target->update(['status' => StudentCorrectionStatus::Cancelled]);
            StudentCorrectionAudit::log($actor, $target, 'correction_cancelled');

            return $target;
        }, 5);
    }
}
