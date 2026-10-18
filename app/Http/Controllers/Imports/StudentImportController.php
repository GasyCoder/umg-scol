<?php

namespace App\Http\Controllers\Imports;

use App\Actions\Imports\AnalyzeStudentImport;
use App\Actions\Imports\CommitStudentImport;
use App\Actions\Imports\CorrectStudentImportRow;
use App\Actions\Imports\UploadStudentImport;
use App\Enums\ImportKind;
use App\Enums\StudentImportRowStatus;
use App\Enums\StudentImportStatus;
use App\Exports\Students\CentralImportTemplate;
use App\Exports\Students\EstablishmentTemplateWorkbook;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\AnalyzeStudentImportRequest;
use App\Http\Requests\Imports\CorrectStudentImportRowRequest;
use App\Http\Requests\Imports\UploadStudentImportRequest;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentImport;
use App\Support\Imports\ImportProgress;
use App\Support\Queue\QueueWorkers;
use App\Support\StudentImportColumns;
use App\Support\StudentReferenceOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StudentImport::class);
        $kind = ImportKind::tryFrom((string) $request->query('kind'));
        $user = $request->user();
        // Kinds the actor sees in the history, with their count, and those it may import.
        $counts = StudentImport::query()->visibleTo($user)->toBase()->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind');
        $kinds = collect(ImportKind::cases())
            ->filter(fn (ImportKind $item) => $counts->has($item->value) || Gate::allows('handleKind', [StudentImport::class, $item]))
            ->map(fn (ImportKind $item) => ['value' => $item->value, 'label' => $item->label(), 'count' => (int) ($counts[$item->value] ?? 0), 'canCreate' => Gate::allows('handleKind', [StudentImport::class, $item])])->values();

        return Inertia::render('imports/index', [
            'imports' => StudentImport::query()->visibleTo($user)->when($kind !== null, fn ($q) => $q->where('kind', $kind))
                ->select(['id', 'uuid', 'kind', 'original_filename', 'status', 'total_rows', 'valid_rows', 'warning_rows', 'rejected_rows', 'duplicate_rows', 'created_rows', 'updated_rows', 'declared_rows', 'matched_rows', 'skipped_rows', 'establishment_id', 'academic_year_id', 'user_id', 'created_at'])
                ->with(['establishment:id,code,name', 'academicYear:id,name', 'user:id,name'])->latest('id')->paginate(20)->withQueryString(),
            'kinds' => $kinds,
            'filters' => ['kind' => $kind->value ?? ''],
            'canCreate' => Gate::allows('create', StudentImport::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', StudentImport::class);

        return Inertia::render('imports/create', ['initialKind' => ImportKind::tryFrom((string) $request->query('kind'))?->value, 'options' => app(StudentReferenceOptions::class)->forUser($request->user()), 'maxKilobytes' => config('student_imports.max_kilobytes'), 'maxRows' => config('student_imports.max_rows'),
            // Expected columns of the establishment file, shown before upload, with those the analysis requires.
            'columns' => collect(StudentImportColumns::labels())->map(fn ($label, $key) => ['label' => $label, 'required' => in_array($key, StudentImportColumns::required(), true)])->values(),
            // Files the actor may import, each with its expected columns.
            'kinds' => collect(ImportKind::cases())->filter(fn (ImportKind $kind) => Gate::allows('handleKind', [StudentImport::class, $kind]))
                ->map(fn (ImportKind $kind) => ['value' => $kind->value, 'label' => $kind->label(), 'columns' => collect(StudentImportColumns::labels($kind))->map(fn ($label, $key) => ['label' => $label, 'required' => in_array($key, StudentImportColumns::required($kind), true)])->values()])->values()]);
    }

    public function store(UploadStudentImportRequest $request, UploadStudentImport $action): RedirectResponse
    {
        $import = $action->handle($request->user(), $request->file('file'), $request->validated('establishment_id') === null ? null : (int) $request->validated('establishment_id'), (int) $request->validated('academic_year_id'), ImportKind::from($request->validated('kind') ?? 'establishment'));

        return to_route('imports.show', $import);
    }

    public function show(Request $request, StudentImport $studentImport): Response
    {
        Gate::authorize('view', $studentImport);
        $filters = $request->validate(['status' => ['nullable', Rule::enum(StudentImportRowStatus::class)]]);
        $rows = $studentImport->rows()->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))->orderBy('row_number')->paginate(25)->withQueryString();
        $visibleIds = Student::query()->visibleTo($request->user())->whereKey($rows->getCollection()->pluck('matched_student_id')->filter()->all())->when($request->user()->hasRole('establishment'), fn ($q) => $q->whereDoesntHave('studentRecords', fn ($record) => $record->where('establishment_id', '!=', $request->user()->establishment_id)))->pluck('id')->all();
        // Values offered for correction: rows not importable as they are, to the actor who may analyse again.
        $canAnalyze = Gate::allows('analyze', $studentImport);
        // Before confirmation: rows not importable as they are; afterwards: rows that were not imported.
        $completed = $studentImport->status === StudentImportStatus::Completed && Gate::allows('commit', $studentImport);
        $correctable = ($studentImport->status === StudentImportStatus::Ready && $canAnalyze) || $completed;
        $offered = fn ($row) => $completed ? ! in_array($row->resolution, ['imported', 'matched'], true) : $row->status !== StudentImportRowStatus::Valid;
        $fields = array_keys($studentImport->mapping ?? []);
        // Value of the existing record for each field in conflict, shown next to the file value.
        $conflicting = $correctable ? array_values(array_intersect($visibleIds, $rows->getCollection()->filter(fn ($row) => $offered($row) && (collect($row->warnings ?? [])->contains('code', 'official_conflict') || ($row->identity_updates ?? []) !== []))->pluck('matched_student_id')->all())) : [];
        $officials = $conflicting === [] ? collect() : Student::query()->whereKey($conflicting)->get()->keyBy('id');
        $rows->through(function ($row) use ($visibleIds, $studentImport, $correctable, $fields, $officials, $offered) {
            $data = $row->normalized_data ?? [];

            return ['id' => $row->id, 'row_number' => $row->row_number, 'status' => $row->status, 'errors' => $row->errors, 'warnings' => $row->warnings, 'resolution' => $row->resolution, 'matched_student_id' => in_array($row->matched_student_id, $visibleIds, true) ? $row->matched_student_id : null, 'data' => array_intersect_key($data, array_flip(['last_name', 'first_names', 'repeat_code', 'level', 'mention', 'program'])),
                // Not blocking: references the confirmation will create in the establishment.
                'values' => $correctable && $offered($row) ? collect($fields)->mapWithKeys(fn ($field) => [$field => is_scalar($data[$field] ?? null) ? (string) $data[$field] : ''])->all() : null,
                'corrected' => array_keys($row->corrections ?? []),
                'identity_updates' => $row->identity_updates ?? [],
                // The record of the matched student may be replaced by the file value only by an actor allowed to correct it.
                'identity_editable' => ($match = $officials->get($row->matched_student_id)) !== null && Gate::allows('update', $match),
                'official' => ($student = $officials->get($row->matched_student_id)) === null ? null : collect($row->warnings ?? [])->where('code', 'official_conflict')->pluck('field')->merge($row->identity_updates ?? [])->unique()->mapWithKeys(function ($field) use ($student) {
                    $value = $student->getAttribute($field);

                    return [$field => match (true) {
                        $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                        $value instanceof \BackedEnum => (string) $value->value,
                        default => (string) $value,
                    }];
                })->all(),
                'notices' => $row->status !== StudentImportRowStatus::Valid ? [] : array_values(array_filter([
                    is_array($data['_identity_updates'] ?? null) && $data['_identity_updates'] !== [] ? 'La fiche sera mise à jour avec le fichier : '.collect($data['_identity_updates'])->map(fn ($field) => StudentImportColumns::labels($studentImport->kind)[$field] ?? $field)->implode(', ').'.' : null,
                    is_array($data['_new_establishment'] ?? null) ? 'Nouvel établissement « '.$data['_new_establishment']['code'].' — '.$data['_new_establishment']['name'].' » : il sera créé.' : null,
                    filled($data['_new_level'] ?? null) ? 'Nouveau niveau « '.$data['_new_level'].' » : il sera créé.' : null,
                    filled($data['_confirms_record'] ?? null) ? ($studentImport->kind === ImportKind::Pnes
                        ? ($studentImport->status === StudentImportStatus::Completed ? 'Situation déjà enregistrée : marquée boursier PNES par cet import.' : 'Situation déjà enregistrée : elle sera marquée boursier PNES après confirmation de l’import.')
                        : ($studentImport->status === StudentImportStatus::Completed ? 'Étudiant saisi par la Scolarité : retrouvé dans la liste de l’établissement lors de cet import.' : 'Étudiant saisi par la Scolarité et trouvé dans la liste : la situation sera vérifiée automatiquement après confirmation de l’import.')) : null,
                    ...array_map(fn ($text) => 'Non repris — '.$text.'.', $data['_dropped'] ?? []),
                    filled($data['_new_mention'] ?? null) ? 'Nouvelle mention « '.$data['_new_mention'].' » : elle sera créée dans l’établissement.' : null,
                    filled($data['_new_program'] ?? null) ? 'Nouveau parcours « '.$data['_new_program'].' » : il sera créé dans l’établissement.' : null,
                ]))];
        });

        $progress = in_array($studentImport->status, [StudentImportStatus::Analyzing, StudentImportStatus::Committing], true) ? ImportProgress::get($studentImport) : null;
        if ($progress !== null && $progress['phase'] === 'waiting' && $progress['idle'] >= 5) {
            // The page follows a job nobody took: it starts a worker itself (forced after 30 s of waiting).
            QueueWorkers::ensure((string) config('student_imports.queue'), $progress['idle'] >= 30);
        }

        return Inertia::render('imports/show', ['import' => $studentImport->load(['establishment:id,code,name', 'academicYear:id,name', 'user:id,name'])->makeHidden(['stored_path', 'disk']), 'rows' => $rows, 'columns' => StudentImportColumns::labels($studentImport->kind), 'requiredColumns' => StudentImportColumns::required($studentImport->kind), 'filter' => $filters['status'] ?? '', // Running analysis or confirmation: progress of the rows (ImportProgress).
            'progress' => $progress,
            'canAnalyze' => $canAnalyze, 'canCommit' => Gate::allows('commit', $studentImport), 'canExport' => Gate::allows('exportErrors', $studentImport)]);
    }

    public function analyze(AnalyzeStudentImportRequest $request, StudentImport $studentImport, AnalyzeStudentImport $action): RedirectResponse
    {
        $action->run($request->user(), $studentImport, $request->validated('mapping'));

        return to_route('imports.show', $studentImport);
    }

    public function correctRow(CorrectStudentImportRowRequest $request, StudentImport $studentImport, int $row, CorrectStudentImportRow $action): RedirectResponse
    {
        /** @var array<string,string|null> $values */
        $values = $request->validated('values') ?? [];
        /** @var list<string>|null $identityUpdates */
        $identityUpdates = $request->has('identity_updates') ? array_values($request->validated('identity_updates') ?? []) : null;
        $line = $studentImport->rows()->findOrFail($row);
        $outcome = $action->handle($request->user(), $studentImport, $line, $values, $identityUpdates);
        $issues = collect([...($line->refresh()->errors ?? []), ...($line->warnings ?? [])])->pluck('message')->take(2)->implode(' ');
        Inertia::flash('toast', match ($outcome) {
            'reanalysed' => ['type' => 'success', 'message' => 'Ligne corrigée : le fichier est analysé de nouveau.'],
            'imported' => ['type' => 'success', 'message' => 'Ligne '.$line->row_number.' importée : la situation de l’année est créée.'],
            'matched' => ['type' => 'success', 'message' => 'Ligne '.$line->row_number.' importée : la situation existante est mise à jour.'],
            default => ['type' => 'error', 'message' => 'Ligne '.$line->row_number.' toujours non importable. '.$issues],
        });

        return back();
    }

    public function commit(Request $request, StudentImport $studentImport, CommitStudentImport $action): RedirectResponse
    {
        $action->run($request->user(), $studentImport);

        return to_route('imports.show', $studentImport);
    }

    public function exportErrors(StudentImport $studentImport): StreamedResponse
    {
        Gate::authorize('exportErrors', $studentImport);

        return response()->streamDownload(function () use ($studentImport) {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }
            fputcsv($stream, ['Ligne', 'Statut', 'Champ', 'Code', 'Message'], ';', '"', '');
            $studentImport->rows()->where('status', '!=', 'valid')->chunkById(200, function ($rows) use ($stream) {
                foreach ($rows as $row) {
                    foreach (array_merge($row->errors ?? [], $row->warnings ?? []) ?: [['field' => 'record', 'code' => 'duplicate', 'message' => 'Situation identique déjà présente.']] as $issue) {
                        $cells = [$row->row_number, $row->status->value, $issue['field'], $issue['code'], $issue['message']];
                        $cells = array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value, $cells);
                        fputcsv($stream, $cells, ';', '"', '');
                    }
                }
            });
            fclose($stream);
        }, 'import-'.$studentImport->id.'-erreurs.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /**
     * Empty establishment file (official columns, drop-downs, BOURSIER/TAUX formulas) for the
     * actor's establishment, or the one chosen by a global actor, and the current academic year.
     */
    public function template(Request $request): StreamedResponse
    {
        $kind = ImportKind::tryFrom((string) $request->query('kind', 'establishment')) ?? ImportKind::Establishment;
        Gate::authorize('handleKind', [StudentImport::class, $kind]);
        if ($kind !== ImportKind::Establishment) {
            return CentralImportTemplate::download($kind);
        }
        $establishmentId = $request->user()->hasRole('establishment') ? $request->user()->establishment_id : ($request->integer('establishment_id') ?: null);
        $establishment = $establishmentId === null ? null : Establishment::query()->find($establishmentId);
        $year = AcademicYear::query()->where('is_current', true)->where('is_active', true)->first();
        $book = (new EstablishmentTemplateWorkbook($establishment, $year))->build();

        return EstablishmentTemplateWorkbook::download($book, 'modele-etudiants'.($establishment === null ? '' : '-'.Str::slug($establishment->code)).'.xlsx');
    }
}
