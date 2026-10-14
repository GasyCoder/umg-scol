<?php

namespace App\Policies;

use App\Models\User;
use App\Support\StudentPortalAccess;

class ScholarshipCampaignPolicy
{
    /** Reading campaigns is needed by file processing; administration stays with campaigns.manage. */
    public function viewAny(User $user): bool
    {
        return StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && ($user->can('campaigns.manage') || $user->can('scholarships.view'));
    }

    public function manage(User $user): bool
    {
        return StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && $user->can('campaigns.manage');
    }
}
