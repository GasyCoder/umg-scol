<?php

namespace App\Http\Controllers\Imports;

use App\Actions\Imports\AnalyzeStudentImport;
use App\Actions\Imports\CommitStudentImport;
use App\Actions\Imports\UploadStudentImport;
use App\Enums\StudentImportRowStatus;
use App\Exports\Students\EstablishmentTemplateWorkbook;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\AnalyzeStudentImportRequest;
use App\Http\Requests\Imports\UploadStudentImportRequest;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentImport;
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

        return Inertia::render('imports/index', ['imports' => StudentImport::query()->visibleTo($request->user())->select(['id', 'uuid', 'original_filename', 'status', 'total_rows', 'valid_rows', 'warning_rows', 'rejected_rows', 'duplicate_rows', 'created_rows', 'updated_rows', 'declared_rows', 'skipped_rows', 'establishment_id', 'academic_year_id', 'user_id', 'created_at'])->with(['establishment:id,code,name', 'academicYear:id,name', 'user:id,name'])->latest('id')->paginate(20), 'canCreate' => Gate::allows('create', StudentImport::class)]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', StudentImport::class);

        return Inertia::render('imports/create', ['options' => app(StudentReferenceOptions::class)->forUser($request->user()), 'maxKilobytes' => config('student_imports.max_kilobytes'), 'maxRows' => config('student_imports.max_rows'),
            // Expected columns of the establishment file, shown before upload, with those the analysis requires.
            'columns' => collect(StudentImportColumns::labels())->map(fn ($label, $key) => ['label' => $label, 'required' => in_array($key, StudentImportColumns::required(), true)])->values()]);
    }

    public function store(UploadStudentImportRequest $request, UploadStudentImport $action): RedirectResponse
    {
        $import = $action->handle($request->user(), $request->file('file'), $request->validated('establishment_id') === null ? null : (int) $request->validated('establishment_id'), (int) $request->validated('academic_year_id'));

        return to_route('imports.show', $import);
    }

    public function show(Request $request, StudentImport $studentImport): Response
    {
        Gate::authorize('view', $studentImport);
        $filters = $request->validate(['status' => ['nullable', Rule::enum(StudentImportRowStatus::class)]]);
        $rows = $studentImport->rows()->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))->orderBy('row_number')->paginate(25)->withQueryString();
        $visibleIds = Student::query()->visibleTo($request->user())->whereKey($rows->getCollection()->pluck('matched_student_id')->filter()->all())->when($request->user()->hasRole('establishment'), fn ($q) => $q->whereDoesntHave('studentRecords', fn ($record) => $record->where('establishment_id', '!=', $request->user()->establishment_id)))->pluck('id')->all();
        $rows->through(function ($row) use ($visibleIds) {
            $data = $row->normalized_data ?? [];

            return ['id' => $row->id, 'row_number' => $row->row_number, 'status' => $row->status, 'errors' => $row->errors, 'warnings' => $row->warnings, 'resolution' => $row->resolution, 'matched_student_id' => in_array($row->matched_student_id, $visibleIds, true) ? $row->matched_student_id : null, 'data' => array_intersect_key($data, array_flip(['last_name', 'first_names', 'repeat_code', 'level', 'mention', 'program'])),
                // Not blocking: references the confirmation will create in the establishment.
                'notices' => $row->status !== StudentImportRowStatus::Valid ? [] : array_values(array_filter([
                    filled($data['_new_level'] ?? null) ? 'Nouveau niveau « '.$data['_new_level'].' » : il sera créé.' : null,
                    filled($data['_new_mention'] ?? null) ? 'Nouvelle mention « '.$data['_new_mention'].' » : elle sera créée dans l’établissement.' : null,
                    filled($data['_new_program'] ?? null) ? 'Nouveau parcours « '.$data['_new_program'].' » : il sera créé dans l’établissement.' : null,
                ]))];
        });

        return Inertia::render('imports/show', ['import' => $studentImport->load(['establishment:id,code,name', 'academicYear:id,name', 'user:id,name'])->makeHidden(['stored_path', 'disk']), 'rows' => $rows, 'columns' => StudentImportColumns::labels(), 'requiredColumns' => StudentImportColumns::required(), 'filter' => $filters['status'] ?? '', 'canAnalyze' => Gate::allows('analyze', $studentImport), 'canCommit' => Gate::allows('commit', $studentImport), 'canExport' => Gate::allows('exportErrors', $studentImport)]);
    }

    public function analyze(AnalyzeStudentImportRequest $request, StudentImport $studentImport, AnalyzeStudentImport $action): RedirectResponse
    {
        $action->handle($request->user(), $studentImport, $request->validated('mapping'));

        return to_route('imports.show', $studentImport);
    }

    public function commit(Request $request, StudentImport $studentImport, CommitStudentImport $action): RedirectResponse
    {
        $action->handle($request->user(), $studentImport);

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
        Gate::authorize('create', StudentImport::class);
        $establishmentId = $request->user()->hasRole('establishment') ? $request->user()->establishment_id : ($request->integer('establishment_id') ?: null);
        $establishment = $establishmentId === null ? null : Establishment::query()->find($establishmentId);
        $year = AcademicYear::query()->where('is_current', true)->where('is_active', true)->first();
        $book = (new EstablishmentTemplateWorkbook($establishment, $year))->build();

        return EstablishmentTemplateWorkbook::download($book, 'modele-etudiants'.($establishment === null ? '' : '-'.Str::slug($establishment->code)).'.xlsx');
    }
}
