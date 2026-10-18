<?php

namespace App\Actions\Students;

use App\Enums\SituationVerification;
use App\Enums\StudentRecordStatus;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Validation by the central office of a situation it entered: only once the establishment's
 * imported list has confirmed it ("matched"). Audited through the StudentRecord log.
 */
class VerifyStudentRecord
{
    /** @return bool false when the situation was already verified */
    public function handle(User $actor, StudentRecord $record): bool
    {
        return DB::transaction(function () use ($actor, $record): bool {
            $target = StudentRecord::query()->lockForUpdate()->findOrFail($record->id);
            Gate::forUser($actor)->authorize('verify', $target);
            if ($target->verification === SituationVerification::Verified) {
                return false;
            }
            if ($target->verification !== SituationVerification::Matched) {
                throw ValidationException::withMessages(['verification' => 'Cette situation n’a pas encore été trouvée dans la liste importée de l’établissement.']);
            }
            // Validated: the situation of the year becomes active.
            $target->update(['verification' => SituationVerification::Verified, ...($target->status === StudentRecordStatus::Pending ? ['status' => StudentRecordStatus::Active] : [])]);

            return true;
        });
    }
}
