<?php

namespace App\Http\Controllers\References;

use App\Actions\References\SaveEstablishment;
use App\Http\Controllers\Controller;
use App\Http\Requests\References\ReferenceListRequest;
use App\Http\Requests\References\ReferenceStatusRequest;
use App\Http\Requests\References\SaveEstablishmentRequest;
use App\Models\Establishment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'records' => $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString()->through(fn (Establishment $e) => [...$e->toArray(), 'logo_url' => $e->logoUrl()]),
            'filters' => $request->only(['search', 'establishment_id']),
            'canCreate' => Gate::allows('create', Establishment::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Establishment::class);

        return Inertia::render('references/establishments/form', ['record' => null, 'types' => Establishment::TYPES]);
    }

    public function store(SaveEstablishmentRequest $request, SaveEstablishment $action): RedirectResponse
    {
        $action->handle(null, $request->safe()->except(['logo', 'remove_logo']), $request->file('logo'), false);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel créé.']);

        return to_route('references.establishments.index');
    }

    public function edit(Request $request, Establishment $establishment): Response
    {
        Gate::authorize('update', $establishment);

        return Inertia::render('references/establishments/form', ['record' => [...$establishment->toArray(), 'logo_url' => $establishment->logoUrl()], 'types' => Establishment::TYPES]);
    }

    public function update(SaveEstablishmentRequest $request, Establishment $establishment, SaveEstablishment $action): RedirectResponse
    {
        $action->handle($establishment, $request->safe()->except(['logo', 'remove_logo']), $request->file('logo'), $request->boolean('remove_logo'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Référentiel modifié.']);

        return to_route('references.establishments.index');
    }

    public function status(ReferenceStatusRequest $request, Establishment $establishment): RedirectResponse
    {
        $establishment->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut mis à jour.']);

        return to_route('references.establishments.index');
    }

    /** Logo served to staff who may see the establishment; never from a public URL. */
    public function logo(Establishment $establishment): StreamedResponse
    {
        Gate::authorize('view', $establishment);
        $disk = Storage::disk(Establishment::LOGO_DISK);
        abort_if($establishment->logo_path === null || ! $disk->exists($establishment->logo_path), 404);

        return $disk->response($establishment->logo_path, null, ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}
