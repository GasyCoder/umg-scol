<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Support\StudentPortalAccess;

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

    public function export(User $user): bool
    {
        return $this->access($user, 'students.export') && $this->access($user, 'students.viewAny');
    }

    public function create(User $user): bool
    {
        return ($this->access($user, 'students.create') && $this->access($user, 'student_records.create')) || $this->registersPending($user);
    }

    /** Central office entry: the situation waits for the establishment's list. */
    public function registersPending(User $user): bool
    {
        return ! $user->hasRole('establishment') && $this->access($user, 'students.register_pending');
    }

    public function update(User $user, Student $student): bool
    {
        return $this->view($user, $student) && $this->access($user, 'students.update') && $this->canManageIdentity($user, $student);
    }

    public function deactivate(User $user, Student $student): bool
    {
        return $this->view($user, $student) && $this->access($user, 'students.deactivate') && $this->canManageIdentity($user, $student);
    }

    /** The linked student changes their own contact details (phone, address) from the portal (ADR-089). */
    public function updateOwnContact(User $user, Student $student): bool
    {
        return StudentPortalAccess::student($user) && $user->hasVerifiedEmail() && $user->student_id === $student->id && $student->is_active;
    }

    /** Email asking the student to complete or check their record, within the visible perimeter. */
    public function notify(User $user, Student $student): bool
    {
        return $this->view($user, $student) && $this->access($user, 'students.notify');
    }

    /** Reception counter: whoever receives paper files sees the identity of the student handing one in. */
    public function viewAtReception(User $user, Student $student): bool
    {
        return $this->access($user, 'scholarships.receive') && Student::query()->visibleTo($user)->whereKey($student->id)->exists();
    }

    /** Reception counter: completes the identity with students.update, within the same identity perimeter. */
    public function updateAtReception(User $user, Student $student): bool
    {
        return $this->viewAtReception($user, $student) && $this->access($user, 'students.update') && $this->canManageIdentity($user, $student);
    }

    private function canManageIdentity(User $user, Student $student): bool
    {
        return ! $user->hasRole('establishment') || ! $student->studentRecords()->where('establishment_id', '!=', $user->establishment_id)->exists();
    }
}
