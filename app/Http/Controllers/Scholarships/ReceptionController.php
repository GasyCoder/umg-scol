<?php

namespace App\Http\Controllers\Scholarships;

use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Actions\Students\SaveStudentIdentity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scholarships\ReceiveApplicationRequest;
use App\Http\Requests\Scholarships\UpdateReceptionStudentRequest;
use App\Models\AcademicYear;
use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\Branding;
use App\Support\Scholarships\DdbLabel;
use App\Support\Scholarships\DdbNumber;
use App\Support\Scholarships\ReceptionActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reception of the paper files of the national scholarship, for the current academic year
 * (ADR-085): no campaign to choose, the year marked « courante » is the one received.
 */
class ReceptionController extends Controller
{
    /** Identity fields the paper file needs: shown as « à compléter » when empty. */
    private const EXPECTED = ['first_names', 'sex', 'birth_date', 'mother_name', 'cin', 'cin_issued_at', 'cin_issued_place', 'nationality', 'phone', 'email', 'address', 'bac_series', 'bac_year'];

    public function index(Request $request): Response
    {
        Gate::authorize('receive', ScholarshipApplication::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']], ['max' => 'La recherche doit contenir au plus :max caractères.']);
        $user = $request->user();
        $year = AcademicYear::receiving();
        $search = trim((string) ($filters['search'] ?? ''));

        return Inertia::render('scholarships/reception', [
            'year' => $year?->only(['id', 'name']), 'search' => $search,
            // Live search: the page reloads only these props while the agent types.
            'results' => fn () => $year !== null && mb_strlen($search) >= 2 ? $this->results($user, $year, $search) : [],
            'activity' => fn () => ReceptionActivity::for($user, $year),
            // A search without result offers to add the student (central office entry, pending its establishment's list).
            'canCreateStudent' => Gate::allows('create', Student::class),
        ]);
    }

    /** The student's file opened from the search, on its own page: identity to complete and paper file reception. */
    public function showStudent(Request $request, Student $student): Response
    {
        Gate::authorize('receive', ScholarshipApplication::class);
        Gate::authorize('viewAtReception', $student);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $year = AcademicYear::receiving();
        $search = trim((string) ($filters['search'] ?? ''));

        $establishment = $year !== null ? $student->studentRecords()->where('academic_year_id', $year->id)->whereIn('status', ['active', 'pending'])->with('establishment:id,code')->first()?->establishment : null;
        $labelTexts = DdbLabel::texts();

        return Inertia::render('scholarships/reception-student', [
            'student' => $this->student($request->user(), $year, $student),
            'year' => $year?->only(['id', 'name']),
            // Indicative: the number is given at reception, with the sequence of that moment.
            'nextDdb' => $year !== null ? DdbNumber::for($year, $establishment, $year->last_application_sequence + 1) : null,
            // Shown in the shape of the paper label, the number on the line the agent fills by hand.
            'ddbLabel' => $year !== null ? [
                ...DdbNumber::parts($year, $establishment?->code, $year->last_application_sequence + 1),
                'texts' => [...$labelTexts, 'institution' => Branding::get()['institution_name'], 'urlLabel' => DdbLabel::urlLabel($labelTexts['url'])],
                'qr' => DdbLabel::qr($labelTexts['url']),
            ] : null,
            // Back to the same search, as the agent left it.
            'backUrl' => '/scholarships/reception'.($search !== '' ? '?'.http_build_query(['search' => $search]) : ''),
        ]);
    }

    public function store(ReceiveApplicationRequest $request, ReceiveScholarshipApplication $action): RedirectResponse
    {
        $application = $action->handle($request->user(), $request->integer('student_id'), $request->validated('ddb_number'), $request->validated('internal_notes'));
        // The DDB number is copied by hand on the label of the paper file: given in the confirmation,
        // then back to the search for the next student.
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dossier reçu : N° DDB '.$application->reference().'.']);

        return to_route('scholarships.reception');
    }

    /** Completes the identity of the student at the counter (autosaved); same rules, locks and audit as the student form. */
    public function updateStudent(UpdateReceptionStudentRequest $request, Student $student, SaveStudentIdentity $action): RedirectResponse
    {
        Gate::authorize('receive', ScholarshipApplication::class);
        $action->handle($request->user(), $student, $request->validated(), 'updateAtReception');
        // Saved automatically while the agent types: the page shows the state itself, no toast.

        return back();
    }

    /** @return array<int,array<string,mixed>> */
    private function results(User $user, AcademicYear $year, string $search): array
    {
        // Server-side search, capped: the secretariat refines the query instead of browsing the base.
        // Minimal preview (no CIN, contacts or address): the full file is opened on demand.
        return Student::query()->matching($search, $user)
            ->with([
                'studentRecords' => fn ($q) => $q->where('academic_year_id', $year->id)->with(['establishment:id,name', 'level:id,name', 'program:id,name']),
                // The file of the year, if any: one scholarship request per academic year.
                'scholarshipApplications' => fn ($q) => $q->where('academic_year_id', $year->id)->select(['id', 'uuid', 'student_id', 'application_number', 'ddb_number']),
            ])->orderBy('last_name')->orderBy('first_names')->limit(20)->get()
            ->map(fn (Student $student) => [
                'id' => $student->id, 'uuid' => $student->uuid, 'last_name' => $student->last_name, 'first_names' => $student->first_names,
                'birth_date' => $student->birth_date?->format('Y-m-d'), 'is_active' => $student->is_active,
                'records' => $student->studentRecords->map(fn ($record) => $this->record($record))->values()->all(),
                // Code bourse: the paper file of a scholarship holder carries it.
                'scholarship_code' => $student->scholarship_code,
                // Names of the empty fields only, never values.
                'missing' => $this->missing($student),
                'application' => ($found = $student->scholarshipApplications->first()) !== null ? ['id' => $found->id, 'uuid' => $found->uuid, 'reference' => $found->reference()] : null,
            ])->values()->all();
    }

    /**
     * Full file of one student: identity, situations and scholarship files.
     *
     * @return array<string,mixed>
     */
    private function student(User $user, ?AcademicYear $year, Student $student): array
    {
        $records = $student->studentRecords()->visibleTo($user)->with(['academicYear:id,name', 'establishment:id,name', 'level:id,name', 'program:id,name'])->orderByDesc('academic_year_id')->orderByDesc('id')->get();
        $applications = $student->scholarshipApplications()->with('academicYear:id,name')->latest('received_at')->get();
        // UIN and bank account (PNES): central office and superadmin only, never establishments.
        $pnes = $user->seesScholarshipRegistry() ? ['uin' => $student->uin, 'bank_account' => $student->bank_account] : [];
        $current = $year !== null ? $applications->firstWhere('academic_year_id', $year->id) : null;

        return [
            ...$student->only(['id', 'uuid', 'last_name', 'first_names', 'mother_name', 'cin', 'cin_issued_place', 'scholarship_code', 'nationality', 'phone', 'email', 'address', 'bac_series', 'bac_year', 'is_active']), ...$pnes,
            'sex' => $student->sex?->value, 'birth_date' => $student->birth_date?->format('Y-m-d'), 'cin_issued_at' => $student->cin_issued_at?->format('Y-m-d'),
            'cin_is_standard' => $student->hasStandardCin(), 'missing' => $this->missing($student),
            'records' => $records->map(fn ($record) => [...$this->record($record), 'academic_year' => $record->academicYear->name, 'is_reception_year' => $record->academic_year_id === $year?->id])->values()->all(),
            'applications' => $applications->map(fn (ScholarshipApplication $a) => ['uuid' => $a->uuid, 'reference' => $a->reference(), 'academic_year' => $a->academicYear->name, 'status' => $a->status->value, 'status_label' => $a->status->label(), 'received_at' => $a->received_at->toIso8601String()])->values()->all(),
            'canUpdate' => Gate::forUser($user)->allows('updateAtReception', $student),
            // The file of the year being received: a second one is refused (one request per academic year).
            'yearApplication' => $current !== null ? ['uuid' => $current->uuid, 'reference' => $current->reference()] : null,
            'blockReason' => $year !== null ? $this->blockReason($student, $records->where('academic_year_id', $year->id)) : 'Aucune année universitaire courante : la réception des dossiers est fermée.',
        ];
    }

    /** @return array<string,mixed> */
    private function record(StudentRecord $record): array
    {
        return ['establishment' => $record->establishment->name, 'level' => $record->level->name, 'program' => $record->program?->name, 'status' => $record->status->value, 'source' => $record->source->value, 'source_label' => $record->source->label(), 'verification' => $record->verification->value, 'verification_label' => $record->verification->label(), 'is_scholarship_holder' => $record->is_scholarship_holder];
    }

    /** @param Collection<int,StudentRecord> $records */
    private function blockReason(Student $student, Collection $records): ?string
    {
        $active = $records->filter(fn (StudentRecord $record) => in_array($record->status->value, ['active', 'pending'], true));

        return match (true) {
            ! $student->is_active => 'Fiche étudiant inactive : réception impossible.',
            $records->isEmpty() => 'Aucune situation pour l’année universitaire en cours : l’établissement doit d’abord déclarer l’étudiant.',
            $active->isEmpty() => 'Situation inactive pour l’année universitaire en cours : contactez l’établissement.',
            $active->count() > 1 => 'Plusieurs situations actives pour l’année : à régulariser avant réception.',
            default => null,
        };
    }

    /** @return list<string> */
    private function missing(Student $student): array
    {
        return array_values(array_filter(self::EXPECTED, fn (string $field) => blank($student->getAttribute($field))));
    }
}
