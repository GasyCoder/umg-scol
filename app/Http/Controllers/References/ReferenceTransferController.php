<?php

namespace App\Http\Controllers\References;

use App\Actions\References\ImportReferences;
use App\Exports\References\ReferenceWorkbook;
use App\Http\Controllers\Controller;
use App\Http\Requests\References\ImportReferenceFileRequest;
use App\Http\Requests\References\ReferenceListRequest;
use App\Support\References\ReferenceFileReader;
use App\Support\References\ReferenceImportAnalyzer;
use App\Support\References\ReferenceImportStore;
use App\Support\References\ReferenceTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Model download, export and previewed import of mentions and programs ({kind} = mentions|programs). */
class ReferenceTransferController extends Controller
{
    public function __construct(private ReferenceImportStore $store) {}

    public function template(Request $request, string $kind): BinaryFileResponse
    {
        Gate::authorize('create', ReferenceTransfer::model($kind));

        return Excel::download(new ReferenceWorkbook($kind, $request->user()), 'modele-'.$kind.'.xlsx')
            ->setPrivate()->setMaxAge(0);
    }

    public function export(ReferenceListRequest $request, string $kind): BinaryFileResponse
    {
        Gate::authorize('viewAny', ReferenceTransfer::model($kind));
        $filters = $request->only(['search', 'establishment_id']);
        $rows = ReferenceTransfer::exportRows($kind, $request->user(), $filters);
        activity('references')->causedBy($request->user())->event('references_exported')
            ->withProperties(['kind' => $kind, 'filters' => array_keys(array_filter($filters, 'filled')), 'total_rows' => count($rows)])->log('references_exported');

        return Excel::download(new ReferenceWorkbook($kind, $request->user(), $rows), $kind.'-'.now()->format('Ymd-His').'.xlsx')
            ->setPrivate()->setMaxAge(0);
    }

    public function analyze(ImportReferenceFileRequest $request, string $kind, ReferenceFileReader $reader, ReferenceImportAnalyzer $analyzer): RedirectResponse
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $input = $reader->read($file, $kind);
        $token = $this->store->put($request->user(), $kind, mb_substr(basename($file->getClientOriginalName()), 0, 255), $input, $analyzer->analyze($request->user(), $kind, $input));

        return to_route('references.transfer.preview', ['kind' => $kind, 'token' => $token]);
    }

    public function preview(Request $request, string $kind, string $token): Response
    {
        Gate::authorize('create', ReferenceTransfer::model($kind));
        $pending = $this->store->get($request->user(), $kind, $token);
        $counts = $pending['analysis']['counts'];

        return Inertia::render('references/import-preview', [
            'kind' => $kind, 'title' => ReferenceTransfer::title($kind), 'token' => $token, 'filename' => $pending['filename'],
            'expiresAt' => $pending['expires_at'], 'counts' => $counts, 'canConfirm' => $counts['create'] + $counts['update'] > 0,
            'rows' => array_map(fn (array $row): array => [
                'line' => $row['line'], 'establishment_code' => $row['establishment_code'], 'mention_code' => $row['mention_code'] ?? null,
                'code' => $row['code'], 'name' => $row['name'], 'status' => $row['status'], 'reason' => $row['reason'],
            ], $pending['analysis']['rows']),
        ]);
    }

    public function confirm(Request $request, string $kind, string $token, ImportReferences $import, ReferenceImportAnalyzer $analyzer): RedirectResponse
    {
        Gate::authorize('create', ReferenceTransfer::model($kind));
        $pending = $this->store->get($request->user(), $kind, $token);
        $counts = $pending['analysis']['counts'];
        if ($counts['create'] + $counts['update'] === 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Aucune ligne à créer ou à mettre à jour.']);

            return to_route('references.transfer.preview', ['kind' => $kind, 'token' => $token]);
        }
        $result = $import->handle($request->user(), $kind, $pending['input'], $pending['fingerprint']);
        if ($result === null) {
            $this->store->put($request->user(), $kind, $pending['filename'], $pending['input'], $analyzer->analyze($request->user(), $kind, $pending['input']), $token);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Le référentiel a changé depuis l’analyse. Vérifiez la prévisualisation mise à jour avant de confirmer.']);

            return to_route('references.transfer.preview', ['kind' => $kind, 'token' => $token]);
        }
        $this->store->forget($token);
        Inertia::flash('toast', ['type' => 'success', 'message' => sprintf('Import terminé : %d créé(s), %d mis à jour, %d ignoré(s).', $result['created'], $result['updated'], $result['skipped'])]);

        return to_route('references.'.$kind.'.index');
    }
}
