<?php

namespace App\Policies;

use App\Models\StudentLinkRequest;
use App\Models\User;
use App\Support\StudentPortalAccess;

class StudentLinkRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && $user->can('student_links.review');
    }

    public function review(User $user, StudentLinkRequest $request): bool
    {
        return $this->viewAny($user) && StudentLinkRequest::query()->visibleTo($user)->whereKey($request->id)->exists();
    }
}
