<?php

namespace App\Http\Controllers\References;

use App\Http\Controllers\Controller;
use App\Http\Requests\References\ReferenceListRequest;
use App\Http\Requests\References\ReferenceStatusRequest;
use App\Http\Requests\References\SaveDomainRequest;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    public function index(ReferenceListRequest $request): Response
    {
        Gate::authorize('viewAny', Domain::class);
        $search = $request->string('search')->trim()->toString();
        $query = Domain::query();

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        return Inertia::render('references/domains/index', [
            'records' => $query->orderBy('sort_order')->orderBy('code')->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'establishment_id']),
            'canCreate' => Gate::allows('create', Domain::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Domain::class);

        return Inertia::render('references/domains/form', ['record' => null]);
    }

    public function store(SaveDomainRequest $request): RedirectResponse
    {
        Domain::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel créé.']);

        return to_route('references.domains.index');
    }

    public function edit(Request $request, Domain $domain): Response
    {
        Gate::authorize('update', $domain);

        return Inertia::render('references/domains/form', ['record' => $domain]);
    }

    public function update(SaveDomainRequest $request, Domain $domain): RedirectResponse
    {
        $domain->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel modifié.']);

        return to_route('references.domains.index');
    }

    public function status(ReferenceStatusRequest $request, Domain $domain): RedirectResponse
    {
        $domain->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut mis à jour.']);

        return to_route('references.domains.index');
    }
}
