<?php

namespace App\Http\Controllers\Scholarships;

use App\Actions\Scholarships\BulkTransitionScholarshipApplications;
use App\Actions\Scholarships\TransitionScholarshipApplication;
use App\Actions\Scholarships\UpdateScholarshipCheck;
use App\Actions\Students\NotifyStudentInformation;
use App\Enums\ScholarshipApplicationStatus;
use App\Enums\ScholarshipCheckStatus;
use App\Exports\Scholarships\ScholarshipApplicationsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scholarships\ApplicationListRequest;
use App\Http\Requests\Scholarships\BulkTransitionApplicationsRequest;
use App\Http\Requests\Scholarships\TransitionApplicationRequest;
use App\Http\Requests\Scholarships\UpdateCheckRequest;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\User;
use App\Support\ScholarshipApplicationFilters;
use App\Support\Scholarships\ValidationBlockers;
use App\Support\StudentInformationFields;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationController extends Controller
{
    /** Relations of a listed file, shared by the list and its Excel export (no contact, address or reason). */
    private const LIST_RELATIONS = [
        'academicYear:id,name', 'student:id,uuid,birth_date,last_name,first_names,cin,is_active',
        'studentRecord:id,student_id,academic_year_id,establishment_id,level_id,program_id,status,verification',
        'studentRecord.establishment:id,code,name', 'studentRecord.level:id,code,name', 'studentRecord.program:id,name',
    ];

    public function index(ApplicationListRequest $request): Response
    {
        $filters = $request->safe()->only(ScholarshipApplicationFilters::KEYS);
        // Check counters for the reason « Valider » is greyed, without loading every check.
        $query = ScholarshipApplicationFilters::apply(ScholarshipApplication::query()->with(self::LIST_RELATIONS)->withCount([
            'checks', 'checks as checks_pending' => fn ($q) => $q->where('status', ScholarshipCheckStatus::Pending->value),
            'checks as checks_issue' => fn ($q) => $q->where('status', ScholarshipCheckStatus::Issue->value),
        ]), $filters);
        $decisions = array_values(array_filter(ScholarshipApplicationStatus::cases(), fn ($status) => $status !== ScholarshipApplicationStatus::Pending));

        return Inertia::render('scholarships/applications/index', [
            'applications' => $query->orderByDesc('received_at')->orderByDesc('id')->paginate(25)->withQueryString()->through(function (ScholarshipApplication $a) {
                $shown = $a->displayStatus();
                $actions = $a->status->isFinal() ? [] : array_values(array_map(fn ($s) => $s->value, array_filter($a->status->targets(), fn ($s) => Gate::allows('transition', [$a, $s]))));

                return [
                    'id' => $a->id, 'uuid' => $a->uuid, 'reference' => $a->reference(),
                    // Identified by names, birth date and CIN, never by contacts.
                    'student' => [...$a->student->only(['last_name', 'first_names', 'cin']), 'birth_date' => $a->student->birth_date?->format('Y-m-d')],
                    'establishment' => $a->studentRecord->establishment->only(['code', 'name']), 'level' => $a->studentRecord->level->only(['code', 'name']),
                    'status' => $shown['value'], 'status_label' => $shown['label'], 'received_at' => $a->received_at->toIso8601String(),
                    'actions' => $actions,
                    // Quick validation from the list: the checks still to do are confirmed, the rest still blocks.
                    'validationBlockers' => in_array(ScholarshipApplicationStatus::Validated->value, $actions, true) ? ValidationBlockers::for($a, true) : [],
                    'awaiting_list' => $shown['value'] === ScholarshipApplication::AWAITING_LIST,
                ];
            }),
            'filters' => $filters,
            'statusCounts' => ScholarshipApplicationFilters::counts($filters),
            'options' => [
                'academicYears' => AcademicYear::query()->orderByDesc('starts_at')->orderByDesc('id')->get(['id', 'name']),
                'statuses' => [['value' => ScholarshipApplication::AWAITING_LIST, 'label' => ScholarshipApplication::AWAITING_LIST_LABEL], ...array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], ScholarshipApplicationStatus::cases())],
                'establishments' => Establishment::query()->orderBy('name')->get(['id', 'name']),
                'levels' => Level::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            ],
            'canExport' => Gate::allows('export', ScholarshipApplication::class),
            'canReceive' => Gate::allows('receive', ScholarshipApplication::class),
            // Decisions offered on a selection; each file is checked again by the server.
            'bulkActions' => array_values(array_map(fn ($s) => $s->value, array_filter($decisions, fn ($s) => Gate::allows('transition', [new ScholarshipApplication, $s])))),
            // Email the students of a selection about missing or doubtful information (ADR-088).
            'canNotifyStudents' => $request->user()->can('students.notify'),
        ]);
    }

    public function show(ScholarshipApplication $application): Response
    {
        Gate::authorize('view', $application);
        $application->load([
            'academicYear:id,name', 'student:id,uuid,last_name,first_names,sex,birth_date,nationality,bac_year,is_active',
            'studentRecord.establishment:id,name', 'studentRecord.level:id,name', 'studentRecord.program:id,name', 'studentRecord.academicYear:id,name',
            'checks' => fn ($q) => $q->orderBy('id')->with('checker:id,name'), 'history' => fn ($q) => $q->orderBy('id')->with('actor:id,name'), 'receiver:id,name', 'reviewStarter:id,name', 'decider:id,name',
        ]);
        $editable = ! $application->status->isFinal();
        $record = $application->studentRecord;
        // The full record is read only to list the fields to point out; none of its values is sent.
        $student = Student::query()->find($application->student_id);

        return Inertia::render('scholarships/applications/show', [
            'application' => [
                'id' => $application->id, 'uuid' => $application->uuid, 'reference' => $application->reference(),
                'status' => $application->status->value, 'status_label' => $application->status->label(),
                // Shown status in the header: « En attente de la liste » while the situation is not confirmed.
                'shown_status' => $application->displayStatus(), 'decision_reason' => $application->decision_reason, 'internal_notes' => $application->internal_notes,
                'academic_year' => $application->academicYear->name,
                'received' => ['at' => $application->received_at->toIso8601String(), 'by' => $application->receiver->name],
                'review_started' => $application->review_started_at ? ['at' => $application->review_started_at->toIso8601String(), 'by' => $application->reviewStarter?->name] : null,
                'decided' => $application->decided_at ? ['at' => $application->decided_at->toIso8601String(), 'by' => $application->decider?->name] : null,
                'student' => [
                    ...$application->student->only(['uuid', 'last_name', 'first_names', 'nationality', 'bac_year', 'is_active']), 'sex' => $application->student->sex?->value, 'birth_date' => $application->student->birth_date?->format('Y-m-d'),
                    'academic_year' => $record->academicYear->name, 'establishment' => $record->establishment->name, 'level' => $record->level->name, 'program' => $record->program?->name, 'record_status' => $record->status->value,
                    // A situation not yet confirmed by the establishment's list blocks the validation only.
                    'record_verification' => $record->verification->value,
                ],
                'checks' => $application->checks->map(fn ($check) => [
                    'key' => $check->key, 'label' => $check->label(), 'status' => $check->status->value, 'status_label' => $check->status->label(), 'note' => $check->note,
                    'checked' => $check->checked_at ? ['at' => $check->checked_at->toIso8601String(), 'by' => $check->checker?->name] : null,
                ])->values(),
                'history' => $application->history->map(fn ($h) => ['from' => $h->from_status?->label(), 'to' => $h->to_status->label(), 'by' => $h->actor->name, 'at' => $h->created_at->toIso8601String(), 'reason' => $h->reason])->values(),
            ],
            'checkStatuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], ScholarshipCheckStatus::cases()),
            'actions' => $editable ? array_values(array_map(fn ($s) => $s->value, array_filter($application->status->targets(), fn ($s) => Gate::allows('transition', [$application, $s])))) : [],
            'canEditChecks' => $editable && Gate::allows('review', $application),
            'validationBlockers' => ValidationBlockers::for($application),
            'canViewStudent' => Gate::allows('view', $application->student),
            // Email to the student about missing or doubtful information (ADR-088), when the account may.
            'notification' => $student !== null && Gate::allows('notify', $student) ? StudentInformationFields::forStaff($student) : null,
        ]);
    }

    public function transition(TransitionApplicationRequest $request, ScholarshipApplication $application, TransitionScholarshipApplication $action, NotifyStudentInformation $notify): RedirectResponse
    {
        // From the list, « Valider » records the checks still to do as conforming (quick validation).
        $updated = $action->handle($request->user(), $application, ScholarshipApplicationStatus::from($request->validated('status')), $request->validated('reason'), $request->boolean('from_list'));
        $message = 'Dossier '.$updated->reference().' : '.$updated->status->label().'.';
        $warning = false;
        if ($updated->status === ScholarshipApplicationStatus::NeedsCorrection && $request->boolean('notify_student')) {
            $sent = $this->notifyCorrection($request->user(), $updated, $notify);
            $message .= $sent ? ' Étudiant prévenu par email.' : ' Étudiant non prévenu (pas d’email ou droit manquant).';
            $warning = ! $sent;
        }
        Inertia::flash('toast', ['type' => $warning ? 'warning' : 'success', 'message' => $message]);

        // Decided from the list: the agent stays on the list, with its filters.
        return $request->boolean('from_list') ? back() : to_route('scholarships.applications.show', $application);
    }

    public function check(UpdateCheckRequest $request, ScholarshipApplication $application, UpdateScholarshipCheck $action): RedirectResponse
    {
        $action->handle($request->user(), $application, $request->validated('key'), ScholarshipCheckStatus::from($request->validated('status')), $request->validated('note'));

        return to_route('scholarships.applications.show', $application);
    }

    public function export(ApplicationListRequest $request): BinaryFileResponse
    {
        $filters = $request->safe()->only(ScholarshipApplicationFilters::KEYS);
        activity('scholarships')->causedBy($request->user())->event('applications_exported')
            ->withProperties(['filters' => array_keys(array_filter($filters, 'filled')), 'academic_year_id' => $filters['academic_year_id'] ?? null, 'status' => $filters['status'] ?? null])->log('applications_exported');

        return Excel::download(new ScholarshipApplicationsExport($filters, self::LIST_RELATIONS), 'dossiers-bourse-'.now()->format('Ymd-His').'.xlsx', ExcelWriter::XLSX, ['Cache-Control' => 'private, no-store']);
    }

    public function bulk(BulkTransitionApplicationsRequest $request, BulkTransitionScholarshipApplications $action, NotifyStudentInformation $notify): RedirectResponse
    {
        $target = ScholarshipApplicationStatus::from($request->validated('status'));
        $result = $action->handle($request->user(), $target, $request->validated('applications'), $request->validated('reason'));
        $parts = [$result['done'].' dossier(s) : '.mb_strtolower($target->label())];
        if ($result['unchanged'] > 0) {
            $parts[] = $result['unchanged'].' déjà dans cet état';
        }
        if ($result['refused'] > 0) {
            $parts[] = $result['refused'].' refusé(s) (droits, contrôles ou situation non vérifiée)';
        }
        if ($target === ScholarshipApplicationStatus::NeedsCorrection && $request->boolean('notify_student') && $result['done_uuids'] !== []) {
            $sent = ScholarshipApplication::query()->whereIn('uuid', $result['done_uuids'])->with('student')->get()
                ->filter(fn (ScholarshipApplication $application) => $this->notifyCorrection($request->user(), $application, $notify))->count();
            $parts[] = $sent.' étudiant(s) prévenu(s) par email';
        }
        Inertia::flash('toast', ['type' => $result['refused'] > 0 ? 'warning' : 'success', 'message' => implode(' · ', $parts).'.']);

        return back();
    }

    /**
     * « Demander une correction » with the box ticked: the student receives the reason and the fields
     * suggested for their record. A missing email or right never cancels the decision already taken.
     */
    private function notifyCorrection(User $actor, ScholarshipApplication $application, NotifyStudentInformation $notify): bool
    {
        try {
            $notify->handle($actor, $application->student, StudentInformationFields::suggested($application->student), $application->decision_reason, $application);

            return true;
        } catch (AuthorizationException|ValidationException) {
            return false;
        }
    }
}
