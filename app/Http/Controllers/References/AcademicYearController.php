<?php

namespace App\Http\Controllers\References;

use App\Actions\References\SaveAcademicYear;
use App\Http\Controllers\Controller;
use App\Http\Requests\References\ReferenceListRequest;
use App\Http\Requests\References\ReferenceStatusRequest;
use App\Http\Requests\References\SaveAcademicYearRequest;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(ReferenceListRequest $request): Response
    {
        Gate::authorize('viewAny', AcademicYear::class);
        $search = $request->string('search')->trim()->toString();
        $query = AcademicYear::query();

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            });
        }

        return Inertia::render('references/academic-years/index', [
            'records' => $query->orderByDesc('is_current')->orderByDesc('name')->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'establishment_id']),
            'canCreate' => Gate::allows('create', AcademicYear::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', AcademicYear::class);

        return Inertia::render('references/academic-years/form', ['record' => null]);
    }

    public function store(SaveAcademicYearRequest $request): RedirectResponse
    {
        app(SaveAcademicYear::class)->handle(new AcademicYear, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel créé.']);

        return to_route('references.academic-years.index');
    }

    public function edit(Request $request, AcademicYear $academic_year): Response
    {
        Gate::authorize('update', $academic_year);

        return Inertia::render('references/academic-years/form', ['record' => $academic_year]);
    }

    public function update(SaveAcademicYearRequest $request, AcademicYear $academic_year): RedirectResponse
    {
        app(SaveAcademicYear::class)->handle($academic_year, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel modifié.']);

        return to_route('references.academic-years.index');
    }

    public function status(ReferenceStatusRequest $request, AcademicYear $academic_year): RedirectResponse
    {
        app(SaveAcademicYear::class)->handle($academic_year, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut mis à jour.']);

        return to_route('references.academic-years.index');
    }

    public function current(AcademicYear $academic_year): RedirectResponse
    {
        Gate::authorize('update', $academic_year);
        app(SaveAcademicYear::class)->setCurrent($academic_year);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Année courante mise à jour.']);

        return to_route('references.academic-years.index');
    }
}
