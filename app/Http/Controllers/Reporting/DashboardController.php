<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\YearFilterRequest;
use App\Models\AcademicYear;
use App\Support\Landing;
use App\Support\Reporting\InstitutionalDashboard;
use App\Support\Reporting\ReportScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(YearFilterRequest $request): Response|RedirectResponse
    {
        $user = $request->user();
        // Without the dashboard, the account starts on the first module it may open.
        if (! Gate::allows('dashboard.view-ui')) {
            return redirect()->to(Landing::for($user));
        }
        // Only global actors choose a year; an establishment always works on the current year.
        $scope = ReportScope::for($user, $user->hasRole('establishment') ? null : $request->yearId());

        return Inertia::render('dashboard', [
            'dashboard' => (new InstitutionalDashboard($scope))->build(),
            'years' => $scope->isGlobal() ? AcademicYear::query()->orderByDesc('name')->get(['id', 'name', 'is_current']) : [],
        ]);
    }
}
