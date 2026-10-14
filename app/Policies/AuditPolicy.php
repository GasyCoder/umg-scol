<?php

namespace App\Policies;

use App\Models\User;
use App\Support\StudentPortalAccess;
use Spatie\Activitylog\Models\Activity;

/** Audit trail: global read for audits.view holders; no role is hidden from it, developer included. */
class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && $user->can('audits.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->viewAny($user);
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user) && $user->can('audits.export');
    }
}
