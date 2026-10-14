<?php

namespace App\Http\Controllers\References;

use App\Actions\References\SaveInstitutionalChild;
use App\Http\Controllers\Controller;
use App\Http\Requests\References\ReferenceListRequest;
use App\Http\Requests\References\ReferenceStatusRequest;
use App\Http\Requests\References\SaveProgramRequest;
use App\Models\Establishment;
use App\Models\Mention;
use App\Models\Program;
use App\Support\References\ReferenceTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function index(ReferenceListRequest $request): Response
    {
        Gate::authorize('viewAny', Program::class);
        $query = ReferenceTransfer::listQuery('programs', $request->user(), $request->only(['search', 'establishment_id']));

        return Inertia::render('references/programs/index', [
            'records' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'establishment_id']),
            'canCreate' => Gate::allows('create', Program::class), 'establishments' => $this->establishments($request),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Program::class);

        return Inertia::render('references/programs/form', ['record' => null, 'establishments' => $this->establishments($request), 'mentions' => $this->mentions($request)]);
    }

    public function store(SaveProgramRequest $request): RedirectResponse
    {
        app(SaveInstitutionalChild::class)->handle($request->user(), new Program, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel créé.']);

        return to_route('references.programs.index');
    }

    public function edit(Request $request, Program $program): Response
    {
        Gate::authorize('update', $program);

        return Inertia::render('references/programs/form', ['record' => $program, 'establishments' => $this->establishments($request), 'mentions' => $this->mentions($request)]);
    }

    public function update(SaveProgramRequest $request, Program $program): RedirectResponse
    {
        app(SaveInstitutionalChild::class)->handle($request->user(), $program, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel modifié.']);

        return to_route('references.programs.index');
    }

    public function status(ReferenceStatusRequest $request, Program $program): RedirectResponse
    {
        app(SaveInstitutionalChild::class)->handle($request->user(), $program, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut mis à jour.']);

        return to_route('references.programs.index');
    }

    /** @return array<int, array<string, mixed>> */
    private function mentions(Request $request): array
    {
        return Mention::query()->when($request->user()->hasRole('establishment'), fn ($query) => $query->where('establishment_id', $request->user()->establishment_id))
            ->orderBy('name')->get(['id', 'establishment_id', 'code', 'name', 'is_active'])->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function establishments(Request $request): array
    {
        return Establishment::query()->when($request->user()->hasRole('establishment'), fn ($query) => $query->where('id', $request->user()->establishment_id))
            ->orderBy('name')->get(['id', 'code', 'name', 'is_active'])->toArray();
    }
}
