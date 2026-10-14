<?php

namespace App\Policies;

use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentImport;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Reports reuse the module Policies: a section is visible only if its list is, and exported only
 * with the module's existing export permission. No generic reports permission.
 */
class ReportPolicy
{
    /** @return array<string,bool> */
    public static function sections(User $user): array
    {
        $gate = Gate::forUser($user);

        return [
            'students' => $gate->allows('viewAny', Student::class),
            'imports' => $gate->allows('viewAny', StudentImport::class),
            'corrections' => $gate->allows('viewAny', StudentCorrectionRequest::class),
            'scholarships' => $gate->allows('viewAny', ScholarshipApplication::class),
        ];
    }

    public static function view(User $user): bool
    {
        return in_array(true, self::sections($user), true);
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
