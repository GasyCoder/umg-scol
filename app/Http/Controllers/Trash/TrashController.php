<?php

namespace App\Http\Controllers\Trash;

use App\Http\Controllers\Controller;
use App\Support\Trash\TrashCatalogue;
use App\Support\Trash\TrashManager;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('trash.manage-ui');
        $filters = $request->validate(['kind' => ['nullable', Rule::in(array_keys(TrashCatalogue::MODELS))], 'search' => ['nullable', 'string', 'max:100']]);
        $kind = $filters['kind'] ?? 'imports';
        $model = TrashCatalogue::model($kind);
        $query = $model->newQuery()->withoutGlobalScope(SoftDeletingScope::class)->whereNotNull($model->qualifyColumn('deleted_at'));
        if (filled($filters['search'] ?? null)) {
            $field = match ($kind) {
                'students' => 'last_name','imports' => 'original_filename','applications' => 'application_number','student-records' => 'uuid',default => 'name'
            };
            $query->where($field, 'like', '%'.addcslashes($filters['search'], '%_\\').'%');
        }
        $rows = $query->latest('deleted_at')->paginate(20)->withQueryString()->through(fn ($item) => ['uuid' => $item->getAttribute('uuid'), 'label' => TrashCatalogue::label($item), 'deleted_at' => $item->getAttribute('deleted_at'), 'deleted_by' => $item->getAttribute('deleted_by')]);

        return Inertia::render('trash/index', ['kind' => $kind, 'kinds' => TrashCatalogue::LABELS, 'records' => $rows, 'filters' => $filters, 'pendingFileCleanupCount' => DB::table('pending_file_deletions')->count(), 'canPurge' => Gate::allows('trash.purge-ui')]);
    }

    public function move(Request $request, string $kind, string $uuid, TrashManager $manager): RedirectResponse
    {
        $manager->handle($request->user(), $kind, $uuid, 'trash');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Élément déplacé dans la corbeille.']);

        $path = match ($kind) {
            'students' => '/students', 'imports' => '/imports', 'campaigns' => '/scholarships/campaigns', 'applications' => '/scholarships/applications',
            'establishments', 'academic-years', 'levels', 'mentions', 'programs' => '/references/'.$kind, default => null,
        };

        return $path === null ? back() : redirect($path);
    }

    public function restore(Request $request, string $kind, string $uuid, TrashManager $manager): RedirectResponse
    {
        $manager->handle($request->user(), $kind, $uuid, 'restore');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Élément restauré.']);

        return back();
    }

    public function purge(Request $request, string $kind, string $uuid, TrashManager $manager): RedirectResponse
    {
        $manager->handle($request->user(), $kind, $uuid, 'purge');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Élément purgé définitivement.']);

        return back();
    }
}
