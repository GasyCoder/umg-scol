<?php

namespace App\Policies;

use App\Models\StudentImport;
use App\Models\User;

class StudentImportPolicy
{
    private function access(User $user, string $permission): bool
    {
        return ! $user->hasRole('student') && $user->hasAnyRole(['superadmin', 'developer', 'establishment']) && $user->hasUsableEstablishment() && $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->access($user, 'imports.view');
    }

    public function create(User $user): bool
    {
        return $this->access($user, 'imports.create');
    }

    public function view(User $user, StudentImport $import): bool
    {
        return $this->viewAny($user) && (! $user->hasRole('establishment') || $user->establishment_id === $import->establishment_id);
    }

    public function analyze(User $user, StudentImport $import): bool
    {
        return $this->view($user, $import) && $this->create($user);
    }

    public function commit(User $user, StudentImport $import): bool
    {
        return $this->view($user, $import) && $this->access($user, 'imports.commit');
    }

    /** Aggregated import report export, within the actor's perimeter. */
    public function exportReport(User $user): bool
    {
        return $this->viewAny($user) && $this->access($user, 'imports.export_errors');
    }

    public function exportErrors(User $user, StudentImport $import): bool
    {
        return $this->view($user, $import) && $this->access($user, 'imports.export_errors');
    }
}
