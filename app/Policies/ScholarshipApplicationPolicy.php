<?php

namespace App\Policies;

use App\Enums\ScholarshipApplicationStatus;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Support\StudentPortalAccess;

/**
 * Paper files are a central process: access is global and granted only through the
 * scholarships.* permissions (central office, superadmin, developer in the current matrix).
 */
class ScholarshipApplicationPolicy
{
    public function access(User $user, string $permission): bool
    {
        return StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && $user->can('scholarships.view') && $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->access($user, 'scholarships.view');
    }

    public function view(User $user, ScholarshipApplication $application): bool
    {
        return $this->viewAny($user);
    }

    public function receive(User $user): bool
    {
        return $this->access($user, 'scholarships.receive');
    }

    public function export(User $user): bool
    {
        return $this->access($user, 'scholarships.export');
    }

    public function review(User $user, ScholarshipApplication $application): bool
    {
        return $this->access($user, 'scholarships.review');
    }

    public function transition(User $user, ScholarshipApplication $application, ScholarshipApplicationStatus $target): bool
    {
        return $this->access($user, $target->permission());
    }
}
