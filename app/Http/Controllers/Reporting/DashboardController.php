<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\YearFilterRequest;
use App\Models\AcademicYear;
use App\Support\Reporting\InstitutionalDashboard;
use App\Support\Reporting\ReportScope;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(YearFilterRequest $request): Response
    {
        $user = $request->user();
        // Only global actors choose a year; an establishment always works on the current year.
        $scope = ReportScope::for($user, $user->hasRole('establishment') ? null : $request->yearId());

        return Inertia::render('dashboard', [
            'dashboard' => (new InstitutionalDashboard($scope))->build(),
            'years' => $scope->isGlobal() ? AcademicYear::query()->orderByDesc('name')->get(['id', 'name', 'is_current']) : [],
        ]);
    }
}
