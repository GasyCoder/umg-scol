<?php

namespace App\Policies;

use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentImport;
use App\Models\User;
use App\Support\StudentPortalAccess;
use Illuminate\Support\Facades\Gate;

/**
 * Reports: reports.view for the actor's perimeter (platform or establishment), reports.view_own for its
 * own activity only (students it declared, imports it ran, files it received, corrections it reviewed).
 * A section is visible only if its list is, and exported only with the module's export permission.
 */
class ReportPolicy
{
    /** @return array<string,bool> */
    public static function sections(User $user, bool $own = false): array
    {
        $gate = Gate::forUser($user);

        return [
            'students' => $gate->allows('viewAny', Student::class),
            // Perimeter: aggregates of the establishment lists (imports.view); own activity: every file the actor imported.
            'imports' => $gate->allows('viewAny', StudentImport::class) && ($own || $user->can('imports.view')),
            'corrections' => $gate->allows('viewAny', StudentCorrectionRequest::class),
            'scholarships' => $gate->allows('viewAny', ScholarshipApplication::class),
        ];
    }

    /** The account may only see its own activity. */
    public static function ownOnly(User $user): bool
    {
        return ! $user->can('reports.view') && $user->can('reports.view_own');
    }

    public static function view(User $user): bool
    {
        return StudentPortalAccess::internal($user) && ($user->can('reports.view') || $user->can('reports.view_own')) && in_array(true, self::sections($user, self::ownOnly($user)), true);
    }

    public static function export(User $user, string $report): bool
    {
        $gate = Gate::forUser($user);

        return match ($report) {
            'students' => $gate->allows('export', Student::class),
            'imports' => $gate->allows('exportReport', StudentImport::class),
            'scholarships' => $gate->allows('export', ScholarshipApplication::class),
            default => false,
        };
    }
}
