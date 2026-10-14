<?php

namespace App\Policies;

use App\Models\StudentCorrectionRequest;
use App\Models\User;
use App\Support\StudentPortalAccess;
use Illuminate\Support\Facades\Gate;

class StudentCorrectionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && $user->can('corrections.view');
    }

    public function view(User $user, StudentCorrectionRequest $correction): bool
    {
        return $this->viewAny($user) && StudentCorrectionRequest::query()->visibleTo($user)->whereKey($correction->id)->exists();
    }

    /** Approving writes the official identity, so the actor must also be allowed to update that Student. */
    public function review(User $user, StudentCorrectionRequest $correction): bool
    {
        return $this->view($user, $correction) && $user->can('corrections.review') && Gate::forUser($user)->allows('update', $correction->student);
    }
}
