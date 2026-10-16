<?php

namespace App\Http\Controllers\Students;

use App\Actions\StudentPortal\DetachStudentPortal;
use App\Actions\Students\CreateDeclaredStudent;
use App\Actions\Students\SaveStudentIdentity;
use App\Actions\Students\SetStudentStatus;
use App\Exports\Students\EstablishmentTemplateWorkbook;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\CreateStudentRequest;
use App\Http\Requests\Students\StudentListRequest;
use App\Http\Requests\Students\StudentStatusRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Mention;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\ScholarshipEligibility;
use App\Support\StudentConfirmationContext;
use App\Support\StudentReferenceOptions;
use App\Support\Students\StudentBrowser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    /** One list of the visible situations, each with its establishment, filtered in the query string. */
    public function index(StudentListRequest $request): Response
    {
        $user = $request->user();
        $browser = new StudentBrowser($user, $request->validated());
        $step = $browser->step();
        $records = $step !== 'list' ? null : $this->ordered($browser->applyToList(StudentRecord::query()->visibleTo($user)))
            ->with(['student:id,uuid,last_name,first_names,sex,birth_date,is_active', 'establishment:id,code,name', 'level:id,code,name', 'program:id,code,name,mention_id', 'program.mention:id,code,name'])
            ->paginate(25)->withQueryString();

        return Inertia::render('students/index', [
            // Cards step by step (establishment → level → mention → program), or the whole list with filters.
            'step' => $step,
            'cards' => $step === 'list' ? [] : $browser->cards(),
            // Counters of the current selection; the status filter is left out so that they act as tabs.
            'stats' => $this->stats($browser, $user),
            'trail' => $browser->trail(),
            'year' => $browser->year?->only(['id', 'name', 'is_current']),
            'records' => $records,
            'filters' => $request->only(['search', 'academic_year_id', 'establishment_id', 'level_id', 'mention_id', 'program_id', 'status', 'view']),
            'options' => app(StudentReferenceOptions::class)->forUser($user),
            // Filter choices only; mentions are per establishment.
            'mentions' => Mention::query()->when($user->hasRole('establishment'), fn ($q) => $q->where('establishment_id', $user->establishment_id))->orderBy('name')->get(['id', 'establishment_id', 'code', 'name']),
            'canCreate' => Gate::allows('create', Student::class), 'canExport' => Gate::allows('export', Student::class),
        ]);
    }

    /**
     * Declarations of the current selection (same filters as the list, one academic year required) in the
     * establishment file format, read back as is by the import. Active declarations unless a status is chosen.
     */
    public function export(StudentListRequest $request): StreamedResponse
    {
        Gate::authorize('export', Student::class);
        $user = $request->user();
        $browser = new StudentBrowser($user, $request->validated());
        abort_if($browser->year === null, 404);
        $selected = $request->validated('records');
        $records = $this->ordered($browser->applyToList(StudentRecord::query()->visibleTo($user)))
            // Rows ticked in the list are exported as chosen; otherwise active situations unless a status is chosen.
            ->when(filled($selected), fn ($q) => $q->whereIn('student_records.uuid', $selected))
            ->when(blank($selected) && $browser->status === null, fn ($q) => $q->where('student_records.status', 'active'))
            ->with(['student', 'academicYear', 'establishment', 'level', 'program.mention.domain'])->get();
        $establishment = $browser->establishmentId === null ? null : Establishment::query()->find($browser->establishmentId);
        activity('students')->causedBy($user)->event('students_exported')
            ->withProperties(['filters' => array_keys(array_filter($request->validated(), 'filled')), 'academic_year_id' => $browser->year->id, 'establishment_id' => $browser->establishmentId, 'total_rows' => $records->count()])->log('students_exported');
        $book = (new EstablishmentTemplateWorkbook($establishment, $browser->year))->build(array_values($records->map(fn (StudentRecord $record) => EstablishmentTemplateWorkbook::rowFor($record))->all()));

        return EstablishmentTemplateWorkbook::download($book, 'etudiants-'.Str::slug($establishment->code ?? 'tous').'-'.Str::slug($browser->year->name).'.xlsx');
    }

    /** Same export, for the rows ticked in the list (posted: the selection can be long). */
    public function exportSelection(StudentListRequest $request): StreamedResponse
    {
        return $this->export($request);
    }

    /** @return array{total:int,active:int,inactive:int,requests:int,incomplete:int,establishments:int} */
    private function stats(StudentBrowser $browser, User $user): array
    {
        $row = $browser->applyToList(StudentRecord::query()->visibleTo($user), false)->toBase()->selectRaw(
            "count(*) as total, sum(case when student_records.status = 'active' then 1 else 0 end) as active, sum(case when student_records.status = 'active' then 0 else 1 end) as inactive, "
            .'sum(case when student_records.scholarship_request_reference is not null then 1 else 0 end) as requests, sum(case when student_records.repeat_code is null then 1 else 0 end) as incomplete, '
            .'count(distinct student_records.establishment_id) as establishments'
        )->first();

        return ['total' => (int) ($row->total ?? 0), 'active' => (int) ($row->active ?? 0), 'inactive' => (int) ($row->inactive ?? 0), 'requests' => (int) ($row->requests ?? 0), 'incomplete' => (int) ($row->incomplete ?? 0), 'establishments' => (int) ($row->establishments ?? 0)];
    }

    /**
     * Alphabetical order of the list and of the export.
     *
     * @param  Builder<StudentRecord>  $query
     * @return Builder<StudentRecord>
     */
    private function ordered(Builder $query): Builder
    {
        return $query->join('students', 'students.id', '=', 'student_records.student_id')
            ->orderBy('students.last_name')->orderBy('students.first_names')->orderBy('student_records.id')->select('student_records.*');
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Student::class);

        return Inertia::render('students/form', ['student' => null, 'options' => app(StudentReferenceOptions::class)->forUser($request->user())]);
    }

    public function store(CreateStudentRequest $request): RedirectResponse
    {
        $student = app(CreateDeclaredStudent::class)->handle($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Étudiant ajouté et situation de l’année créée.']);

        return to_route('students.show', $student);
    }

    public function show(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);
        Gate::authorize('access', [Student::class, 'student_records.view']);
        $records = $student->studentRecords()->visibleTo($request->user())->with(['academicYear:id,name,is_current,scholarship_max_bac_year', 'establishment:id,code,name', 'level:id,code,name', 'program:id,code,name,mention_id', 'program.mention:id,name', 'declaredBy:id,name'])->orderByDesc('academic_year_id')->orderByDesc('id')->get();

        return Inertia::render('students/show', ['student' => [...$student->toArray(), 'cin_is_standard' => $student->hasStandardCin()],
            // Indicative scholarship rule of the establishment file, per declaration.
            'records' => $records->map(fn ($record) => [...$record->toArray(), 'eligibility' => ScholarshipEligibility::evaluate($student, $record), 'canUpdate' => Gate::allows('update', $record)]),
            'confirmation' => StudentConfirmationContext::load($student)->forStaff(),
            // Whether a portal account is linked; the login email is never shown here.
            'portalLinked' => User::query()->where('student_id', $student->id)->exists(),
            'canDetachPortal' => $request->user()->can('student_links.review'),
            // The page shows the situation of this year first; past years are history.
            'currentYear' => AcademicYear::query()->where('is_current', true)->where('is_active', true)->first(['id', 'name']),
            'canUpdate' => Gate::allows('update', $student), 'canDeactivate' => Gate::allows('deactivate', $student), 'canCreateRecord' => Gate::allows('create', [StudentRecord::class, $student])]);
    }

    public function edit(Request $request, Student $student): Response
    {
        Gate::authorize('update', $student);

        return Inertia::render('students/form', ['student' => $student, 'options' => app(StudentReferenceOptions::class)->forUser($request->user())]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        app(SaveStudentIdentity::class)->handle($request->user(), $student, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identité mise à jour.']);

        return to_route('students.show', $student);
    }

    /** Detaches the portal account linked to the record (wrong person, lost account). */
    public function detachPortal(Request $request, Student $student): RedirectResponse
    {
        app(DetachStudentPortal::class)->handle($request->user(), $student);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Compte portail détaché de la fiche.']);

        return to_route('students.show', $student);
    }

    public function status(StudentStatusRequest $request, Student $student): RedirectResponse
    {
        app(SetStudentStatus::class)->handle($request->user(), $student, $request->boolean('is_active'));

        return to_route('students.show', $student);
    }
}
