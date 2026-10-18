<?php

namespace App\Policies;

use App\Enums\SituationSource;
use App\Enums\SituationVerification;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;

class StudentRecordPolicy
{
    public function view(User $user, StudentRecord $record): bool
    {
        return (new StudentPolicy)->access($user, 'student_records.view') && (! $user->hasRole('establishment') || $user->establishment_id === $record->establishment_id);
    }

    public function create(User $user, Student $student): bool
    {
        return (new StudentPolicy)->view($user, $student) && ((new StudentPolicy)->access($user, 'student_records.create') || (new StudentPolicy)->registersPending($user));
    }

    public function update(User $user, StudentRecord $record): bool
    {
        // The central office may still correct a situation it entered, until it is verified.
        return $this->view($user, $record) && ((new StudentPolicy)->access($user, 'student_records.update')
            || ($record->source === SituationSource::Central && $record->verification !== SituationVerification::Verified && (new StudentPolicy)->registersPending($user)));
    }

    /** Validates a situation entered by the central office, once found in the establishment's list. */
    public function verify(User $user, StudentRecord $record): bool
    {
        return $this->view($user, $record) && ! $user->hasRole('establishment') && (new StudentPolicy)->access($user, 'student_records.verify');
    }
}
