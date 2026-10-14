<?php

namespace App\Http\Middleware;

use App\Models\Activity;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipCampaign;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentImport;
use App\Support\Audit\AuditPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if ($request->getHost() === config('portals.bourse_domain')) {
            return [];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->values()->all() ?? [],
                'scholarships' => [
                    'campaigns' => $request->user() !== null && Gate::allows('viewAny', ScholarshipCampaign::class),
                    'receive' => $request->user() !== null && Gate::allows('receive', ScholarshipApplication::class),
                    'applications' => $request->user() !== null && Gate::allows('viewAny', ScholarshipApplication::class),
                ],
                'canViewCorrections' => $request->user() !== null && Gate::allows('viewAny', StudentCorrectionRequest::class),
                'canViewImports' => $request->user() !== null && Gate::allows('viewAny', StudentImport::class),
                'canViewStudents' => $request->user() !== null && Gate::allows('viewAny', Student::class),
                'canManagePermissions' => $request->user() !== null && Gate::allows('permissions.manage-ui'),
                'canManageTrash' => $request->user() !== null && Gate::allows('trash.manage-ui'),
                'canViewReports' => $request->user() !== null && Gate::allows('reports.view'),
                'canViewAudits' => $request->user() !== null && Gate::allows('viewAny', Activity::class),
                'roleLabel' => $request->user() === null ? null : AuditPresenter::role($request->user()),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
