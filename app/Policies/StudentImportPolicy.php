<?php

namespace App\Policies;

use App\Enums\ImportKind;
use App\Models\StudentImport;
use App\Models\User;

/**
 * Establishment lists: establishments (their own) and global actors. PNES base and
 * pre-registration results: the central office and the superadmin, with their own permission.
 */
class StudentImportPolicy
{
    private function access(User $user, string $permission): bool
    {
        return ! $user->hasRole('student') && $user->hasAnyRole(['superadmin', 'developer', 'establishment', 'central_scholarship_office']) && $user->hasUsableEstablishment() && $user->can($permission);
    }

    /** May work with this kind of file (upload, analyse, confirm). */
    public function handleKind(User $user, ImportKind $kind): bool
    {
        if ($kind !== ImportKind::Establishment && $user->hasRole('establishment')) {
            return false;
        }

        return $this->access($user, $kind->permission());
    }

    public function viewAny(User $user): bool
    {
        return $this->access($user, 'imports.view') || $this->access($user, 'imports.pnes') || $this->access($user, 'imports.preinscription');
    }

    public function create(User $user): bool
    {
        return collect(ImportKind::cases())->contains(fn (ImportKind $kind) => $this->handleKind($user, $kind));
    }

    public function view(User $user, StudentImport $import): bool
    {
        // An establishment list needs imports.view; a central file, its own permission or imports.view.
        $kind = $this->access($user, 'imports.view') || ($import->kind !== ImportKind::Establishment && $this->handleKind($user, $import->kind));

        return $kind && (! $user->hasRole('establishment') || $user->establishment_id === $import->establishment_id);
    }

    public function analyze(User $user, StudentImport $import): bool
    {
        return $this->view($user, $import) && $this->handleKind($user, $import->kind);
    }

    public function commit(User $user, StudentImport $import): bool
    {
        // The central files carry their own permission; an establishment list needs imports.commit.
        return $this->view($user, $import) && $this->handleKind($user, $import->kind)
            && ($import->kind !== ImportKind::Establishment || $this->access($user, 'imports.commit'));
    }

    /** Aggregated import report export, within the actor's perimeter. */
    public function exportReport(User $user): bool
    {
        return $this->viewAny($user) && $this->access($user, 'imports.export_errors');
    }

    public function exportErrors(User $user, StudentImport $import): bool
    {
        return $this->view($user, $import) && ($this->access($user, 'imports.export_errors') || ($import->kind !== ImportKind::Establishment && $this->handleKind($user, $import->kind)));
    }
}
