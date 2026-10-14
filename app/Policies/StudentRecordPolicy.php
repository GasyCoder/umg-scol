<?php

namespace App\Policies;

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
        return (new StudentPolicy)->view($user, $student) && (new StudentPolicy)->access($user, 'student_records.create');
    }

    public function update(User $user, StudentRecord $record): bool
    {
        return $this->view($user, $record) && (new StudentPolicy)->access($user, 'student_records.update');
    }
}
