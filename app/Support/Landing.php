<?php

namespace App\Support;

use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentImport;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;

/**
 * First screen of an account without the dashboard: the first module it may open, in the order of
 * the menu, and its profile when it has none (never a page it would be refused).
 */
final class Landing
{
    public static function for(User $user): string
    {
        $gate = Gate::forUser($user);
        foreach ([
            'students.index' => $gate->allows('viewAny', Student::class),
            'scholarships.reception' => $gate->allows('receive', ScholarshipApplication::class),
            'scholarships.applications.index' => $gate->allows('viewAny', ScholarshipApplication::class),
            'imports.index' => $gate->allows('viewAny', StudentImport::class),
            'corrections.index' => $gate->allows('viewAny', StudentCorrectionRequest::class),
            'reports.index' => $gate->allows('reports.view-ui'),
            'notifications.index' => $gate->allows('viewAny', DatabaseNotification::class),
        ] as $route => $allowed) {
            if ($allowed) {
                return route($route);
            }
        }

        return route('profile.edit');
    }
}
