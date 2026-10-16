<?php

namespace App\Http\Controllers\References;

use App\Actions\References\SaveInstitutionalChild;
use App\Http\Controllers\Controller;
use App\Http\Requests\References\ReferenceListRequest;
use App\Http\Requests\References\ReferenceStatusRequest;
use App\Http\Requests\References\SaveMentionRequest;
use App\Models\Domain;
use App\Models\Establishment;
use App\Models\Mention;
use App\Support\References\ReferenceTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MentionController extends Controller
{
    public function index(ReferenceListRequest $request): Response
    {
        Gate::authorize('viewAny', Mention::class);
        $filters = $request->only(['search', 'establishment_id']);
        $query = ReferenceTransfer::listQuery('mentions', $request->user(), $filters);
        $browse = ReferenceTransfer::browsesEstablishments($request->user(), $filters);

        return Inertia::render('references/mentions/index', [
            // One establishment at a time: its whole list fits on a page.
            'records' => $query->paginate(filled($filters['establishment_id'] ?? null) || $request->user()->hasRole('establishment') ? 100 : 15)->withQueryString(),
            'establishmentCards' => $browse ? ReferenceTransfer::establishmentCards($request->user()) : null,
            'filters' => $request->only(['search', 'establishment_id']),
            'canCreate' => Gate::allows('create', Mention::class), 'establishments' => $this->establishments($request),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Mention::class);

        return Inertia::render('references/mentions/form', ['record' => null, 'establishments' => $this->establishments($request), 'domains' => $this->domains()]);
    }

    public function store(SaveMentionRequest $request): RedirectResponse
    {
        app(SaveInstitutionalChild::class)->handle($request->user(), new Mention, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel créé.']);

        return to_route('references.mentions.index');
    }

    public function edit(Request $request, Mention $mention): Response
    {
        Gate::authorize('update', $mention);

        return Inertia::render('references/mentions/form', ['record' => $mention, 'establishments' => $this->establishments($request), 'domains' => $this->domains()]);
    }

    public function update(SaveMentionRequest $request, Mention $mention): RedirectResponse
    {
        app(SaveInstitutionalChild::class)->handle($request->user(), $mention, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel modifié.']);

        return to_route('references.mentions.index');
    }

    public function status(ReferenceStatusRequest $request, Mention $mention): RedirectResponse
    {
        app(SaveInstitutionalChild::class)->handle($request->user(), $mention, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut mis à jour.']);

        return to_route('references.mentions.index');
    }

    /** @return array<int, array<string, mixed>> */
    private function domains(): array
    {
        return Domain::query()->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name', 'is_active'])->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function establishments(Request $request): array
    {
        return Establishment::query()->when($request->user()->hasRole('establishment'), fn ($query) => $query->where('id', $request->user()->establishment_id))
            ->orderBy('name')->get(['id', 'code', 'name', 'is_active'])->toArray();
    }
}
