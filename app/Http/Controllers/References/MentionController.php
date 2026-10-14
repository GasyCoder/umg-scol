<?php

namespace App\Http\Controllers\References;

use App\Http\Controllers\Controller;
use App\Http\Requests\References\ReferenceListRequest;
use App\Http\Requests\References\ReferenceStatusRequest;
use App\Http\Requests\References\SaveMentionRequest;
use App\Models\Establishment;
use App\Models\Mention;
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
        $search = $request->string('search')->trim()->toString();
        $query = Mention::query()->with('establishment:id,code,name');
        if ($request->user()->hasRole('establishment')) {
            $query->where('establishment_id', $request->user()->establishment_id);
        }
        if ($request->filled('establishment_id')) {
            $query->where('establishment_id', $request->integer('establishment_id'));
        }
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        return Inertia::render('references/mentions/index', [
            'records' => $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'establishment_id']),
            'canCreate' => Gate::allows('create', Mention::class), 'establishments' => $this->establishments($request),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Mention::class);

        return Inertia::render('references/mentions/form', ['record' => null, 'establishments' => $this->establishments($request)]);
    }

    public function store(SaveMentionRequest $request): RedirectResponse
    {
        Mention::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel créé.']);

        return to_route('references.mentions.index');
    }

    public function edit(Request $request, Mention $mention): Response
    {
        Gate::authorize('update', $mention);

        return Inertia::render('references/mentions/form', ['record' => $mention, 'establishments' => $this->establishments($request)]);
    }

    public function update(SaveMentionRequest $request, Mention $mention): RedirectResponse
    {
        $mention->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel modifié.']);

        return to_route('references.mentions.index');
    }

    public function status(ReferenceStatusRequest $request, Mention $mention): RedirectResponse
    {
        $mention->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut mis à jour.']);

        return to_route('references.mentions.index');
    }

    /** @return array<int, array<string, mixed>> */
    private function establishments(Request $request): array
    {
        return Establishment::query()->when($request->user()->hasRole('establishment'), fn ($query) => $query->where('id', $request->user()->establishment_id))
            ->orderBy('name')->get(['id', 'code', 'name', 'is_active'])->toArray();
    }
}
