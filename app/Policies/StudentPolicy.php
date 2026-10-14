<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function access(User $user, string $permission): bool
    {
        return ! $user->hasRole('student') && $user->hasAnyRole(['superadmin', 'developer', 'central_scholarship_office', 'establishment']) && $user->hasUsableEstablishment() && $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->access($user, 'students.viewAny');
    }

    public function view(User $user, Student $student): bool
    {
        return $this->access($user, 'students.view') && Student::query()->visibleTo($user)->whereKey($student->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->access($user, 'students.create') && $this->access($user, 'student_records.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $this->view($user, $student) && $this->access($user, 'students.update') && $this->canManageIdentity($user, $student);
    }

    public function deactivate(User $user, Student $student): bool
    {
        return $this->view($user, $student) && $this->access($user, 'students.deactivate') && $this->canManageIdentity($user, $student);
    }

    private function canManageIdentity(User $user, Student $student): bool
    {
        return ! $user->hasRole('establishment') || ! $student->studentRecords()->where('establishment_id', '!=', $user->establishment_id)->exists();
    }
}
