<?php

namespace App\Support;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\StudentCorrectionStatus;
use App\Enums\StudentImportStatus;
use App\Enums\StudentLinkStatus;
use App\Models\ScholarshipApplication;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentImport;
use App\Models\StudentLinkRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Work waiting for the actor, shown by the top bar bell and the sidebar counters.
 * Computed on read from each module's Policy and visibleTo() perimeter: nothing is stored,
 * so a task disappears as soon as it is handled, and nobody sees counts outside their scope.
 */
class PendingTasks
{
    /**
     * @return array{total: int, items: list<array{key: string, label: string, description: string, count: int, href: string}>}
     */
    public static function forUser(User $user): array
    {
        $gate = Gate::forUser($user);
        $tasks = [];

        if ($gate->allows('viewAny', StudentCorrectionRequest::class) && $user->can('corrections.review')) {
            $tasks[] = [['key' => 'corrections', 'label' => 'Corrections à examiner', 'description' => 'Demandes de correction signalées par des étudiants.', 'href' => '/corrections?status=pending'],
                StudentCorrectionRequest::query()->visibleTo($user)->where('status', StudentCorrectionStatus::Pending)];
        }

        if ($gate->allows('viewAny', StudentLinkRequest::class)) {
            $tasks[] = [['key' => 'student_links', 'label' => 'Rattachements à valider', 'description' => 'Comptes étudiants en attente de rattachement à une fiche.', 'href' => '/student-links'],
                StudentLinkRequest::query()->visibleTo($user)->where('status', StudentLinkStatus::Pending)];
        }

        if ($gate->allows('viewAny', ScholarshipApplication::class) && $user->can('scholarships.review')) {
            $tasks[] = [['key' => 'applications_pending', 'label' => 'Dossiers reçus à vérifier', 'description' => 'Dossiers papier enregistrés, vérification non commencée.', 'href' => '/scholarships/applications?status=pending'],
                ScholarshipApplication::query()->where('status', ScholarshipApplicationStatus::Pending)];
            $tasks[] = [['key' => 'applications_in_review', 'label' => 'Dossiers en vérification', 'description' => 'Vérifications commencées, décision à prendre.', 'href' => '/scholarships/applications?status=in_review'],
                ScholarshipApplication::query()->where('status', ScholarshipApplicationStatus::InReview)];
        }

        if ($gate->allows('viewAny', StudentImport::class) && $user->can('imports.commit')) {
            $tasks[] = [['key' => 'imports_ready', 'label' => 'Imports prêts à confirmer', 'description' => 'Fichiers analysés en attente de confirmation.', 'href' => '/imports'],
                StudentImport::query()->visibleTo($user)->where('status', StudentImportStatus::Ready)];
        }

        if ($tasks === []) {
            return ['total' => 0, 'items' => []];
        }

        // Shared with every internal page: all counters in a single SELECT of COUNT subqueries.
        $select = DB::query();
        foreach ($tasks as $index => [, $query]) {
            $select->selectSub($query->toBase()->selectRaw('COUNT(*)'), 'task_'.$index);
        }
        $counts = (array) $select->first();

        $items = [];
        foreach ($tasks as $index => [$task]) {
            $count = (int) $counts['task_'.$index];
            if ($count > 0) {
                $items[] = [...$task, 'count' => $count];
            }
        }

        return ['total' => array_sum(array_column($items, 'count')), 'items' => $items];
    }
}
