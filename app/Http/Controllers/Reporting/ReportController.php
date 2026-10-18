<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\YearFilterRequest;
use App\Models\AcademicYear;
use App\Policies\ReportPolicy;
use App\Support\CsvExport;
use App\Support\Reporting\InstitutionalReports;
use App\Support\Reporting\ReportScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const COLUMNS = [
        'students' => ['establishment' => 'Établissement', 'level' => 'Niveau', 'program' => 'Parcours', 'active' => 'Situations actives', 'inactive' => 'Situations inactives', 'total' => 'Total'],
        'imports' => ['establishment' => 'Établissement', 'imports' => 'Imports', 'completed' => 'Terminés', 'failed' => 'En échec', 'total_rows' => 'Lignes traitées', 'valid_rows' => 'Valides', 'warning_rows' => 'Avertissements', 'rejected_rows' => 'Rejetées', 'duplicate_rows' => 'Doublons', 'declared_rows' => 'Situations importées'],
        'scholarships' => ['establishment' => 'Établissement', 'level' => 'Niveau', 'pending' => 'Reçus', 'in_review' => 'En vérification', 'validated' => 'Validés', 'needs_correction' => 'Correction demandée', 'rejected' => 'Rejetés', 'total' => 'Total'],
    ];

    public function index(YearFilterRequest $request): Response
    {
        Gate::authorize('reports.view-ui');
        $user = $request->user();
        $own = $this->own($request);
        $scope = ReportScope::for($user, $request->yearId(), $own);
        $reports = new InstitutionalReports($scope);
        $sections = ReportPolicy::sections($user, $own);

        return Inertia::render('reports/index', [
            'scope' => ['year' => $scope->yearSummary(), 'global' => $scope->isGlobal(), 'establishment' => $scope->isGlobal() ? null : $user->establishment()->value('name'), 'own' => $own,
                // Both kinds of report: the actor switches between its perimeter and its own activity.
                'canChoose' => $user->can('reports.view') && $user->can('reports.view_own')],
            'years' => AcademicYear::query()->orderByDesc('name')->get(['id', 'name', 'is_current']),
            'columns' => self::COLUMNS,
            'students' => $sections['students'] ? $reports->students() : null,
            'imports' => $sections['imports'] ? $reports->imports() : null,
            'corrections' => $sections['corrections'] ? $reports->corrections() : null,
            'scholarships' => $sections['scholarships'] ? $reports->scholarships() : null,
            'exports' => collect(['students', 'imports', 'scholarships'])->mapWithKeys(fn ($report) => [$report => ReportPolicy::export($user, $report)]),
        ]);
    }

    public function export(YearFilterRequest $request, string $report): StreamedResponse
    {
        abort_unless(array_key_exists($report, self::COLUMNS), 404);
        Gate::authorize('reports.export-ui', $report);
        $scope = ReportScope::for($request->user(), $request->yearId(), $this->own($request));
        $rows = (new InstitutionalReports($scope))->{$report}();
        activity('reports')->causedBy($request->user())->event('reports_exported')
            ->withProperties(['report' => $report, 'academic_year_id' => $scope->year?->id, 'establishment_id' => $scope->establishmentId, 'own' => $scope->isOwn()])->log('reports_exported');
        $columns = self::COLUMNS[$report];

        return CsvExport::download('rapport-'.$report.'-'.($scope->year->name ?? 'sans-annee').'-'.now()->format('Ymd-His').'.csv', array_values($columns), function (callable $write) use ($rows, $columns) {
            foreach ($rows as $row) {
                $write(array_map(fn ($key) => $row[$key] ?? '', array_keys($columns)));
            }
        });
    }

    /** Own activity: always without reports.view, on request (?scope=mine) with both permissions. */
    private function own(Request $request): bool
    {
        $user = $request->user();

        return ReportPolicy::ownOnly($user) || ($request->query('scope') === 'mine' && $user->can('reports.view_own'));
    }
}
