<?php

namespace App\Http\Controllers\Students;

use App\Actions\Students\CreateDeclaredStudent;
use App\Actions\Students\SaveStudentIdentity;
use App\Actions\Students\SetStudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\CreateStudentRequest;
use App\Http\Requests\Students\StudentListRequest;
use App\Http\Requests\Students\StudentStatusRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentConfirmationContext;
use App\Support\StudentReferenceOptions;
use App\Support\Students\StudentBrowser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(StudentListRequest $request): Response
    {
        $user = $request->user();
        $browser = new StudentBrowser($user, $request->validated());
        $step = $browser->step();
        $records = null;
        if ($step === 'list') {
            $query = $browser->applyToList(StudentRecord::query()->visibleTo($user))
                ->with(['student:id,uuid,student_number,last_name,first_names,is_active', 'academicYear:id,name', 'establishment:id,code,name', 'level:id,code,name', 'program:id,code,name,mention_id', 'program.mention:id,name']);
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            $search = trim($request->string('search')->toString());
            if ($search !== '') {
                $query->whereHas('student', fn ($q) => $q->matching($search));
            }
            $records = $query->orderBy('establishment_id')->orderByDesc('id')->paginate(20)->withQueryString();
        }

        return Inertia::render('students/index', [
            'step' => $step,
            'year' => $browser->year?->only(['id', 'name', 'is_current']),
            'cards' => $step === 'list' ? [] : $browser->cards(),
            'trail' => $browser->trail(),
            'records' => $records,
            'filters' => $request->only(['search', 'academic_year_id', 'establishment_id', 'level_id', 'mention_id', 'program_id', 'status']),
            'options' => app(StudentReferenceOptions::class)->forUser($user), 'canCreate' => Gate::allows('create', Student::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Student::class);

        return Inertia::render('students/form', ['student' => null, 'options' => app(StudentReferenceOptions::class)->forUser($request->user())]);
    }

    public function store(CreateStudentRequest $request): RedirectResponse
    {
        $student = app(CreateDeclaredStudent::class)->handle($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Étudiant ajouté et déclaration annuelle créée.']);

        return to_route('students.show', $student);
    }

    public function show(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);
        Gate::authorize('access', [Student::class, 'student_records.view']);
        $records = $student->studentRecords()->visibleTo($request->user())->with(['academicYear:id,name,is_current', 'establishment:id,code,name', 'level:id,code,name', 'program:id,code,name,mention_id', 'program.mention:id,name', 'declaredBy:id,name'])->orderByDesc('academic_year_id')->orderByDesc('id')->get();

        return Inertia::render('students/show', ['student' => $student, 'records' => $records->map(fn ($record) => [...$record->toArray(), 'canUpdate' => Gate::allows('update', $record)]),
            'confirmation' => StudentConfirmationContext::load($student)->forStaff(),
            // Whether a portal account is linked; the login email is never shown here.
            'portalLinked' => User::query()->where('student_id', $student->id)->exists(),
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

    public function status(StudentStatusRequest $request, Student $student): RedirectResponse
    {
        app(SetStudentStatus::class)->handle($request->user(), $student, $request->boolean('is_active'));

        return to_route('students.show', $student);
    }
}
