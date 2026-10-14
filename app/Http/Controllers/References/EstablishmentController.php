<?php

namespace App\Http\Controllers\References;

use App\Http\Controllers\Controller;
use App\Http\Requests\References\ReferenceListRequest;
use App\Http\Requests\References\ReferenceStatusRequest;
use App\Http\Requests\References\SaveEstablishmentRequest;
use App\Models\Establishment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EstablishmentController extends Controller
{
    public function index(ReferenceListRequest $request): Response
    {
        Gate::authorize('viewAny', Establishment::class);
        $search = $request->string('search')->trim()->toString();
        $query = Establishment::query();
        if ($request->user()->hasRole('establishment')) {
            $query->where('id', $request->user()->establishment_id);
        }
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        return Inertia::render('references/establishments/index', [
            'records' => $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'establishment_id']),
            'canCreate' => Gate::allows('create', Establishment::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Establishment::class);

        return Inertia::render('references/establishments/form', ['record' => null]);
    }

    public function store(SaveEstablishmentRequest $request): RedirectResponse
    {
        Establishment::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel créé.']);

        return to_route('references.establishments.index');
    }

    public function edit(Request $request, Establishment $establishment): Response
    {
        Gate::authorize('update', $establishment);

        return Inertia::render('references/establishments/form', ['record' => $establishment]);
    }

    public function update(SaveEstablishmentRequest $request, Establishment $establishment): RedirectResponse
    {
        $establishment->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel modifié.']);

        return to_route('references.establishments.index');
    }

    public function status(ReferenceStatusRequest $request, Establishment $establishment): RedirectResponse
    {
        $establishment->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut mis à jour.']);

        return to_route('references.establishments.index');
    }
}
