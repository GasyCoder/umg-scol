<?php

namespace App\Http\Controllers\Corrections;

use App\Actions\Corrections\ReviewStudentCorrection;
use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Corrections\CorrectionListRequest;
use App\Http\Requests\Corrections\ReviewCorrectionRequest;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Support\StudentNormalization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CorrectionController extends Controller
{
    /** One row per student, with the fields asked for (ADR-090): several requests of a student never fill the list. */
    public function index(CorrectionListRequest $request): Response
    {
        $user = $request->user();
        $status = $request->string('status')->toString() ?: StudentCorrectionStatus::Pending->value;
        $filtered = fn () => $this->filtered($request, $status);
        $groups = $filtered()->select('student_id')->selectRaw('COUNT(*) as total')->selectRaw('MIN(created_at) as first_at')
            ->groupBy('student_id')->orderBy('first_at')->orderBy('student_id')->paginate(20)->withQueryString();
        $ids = collect($groups->items())->pluck('student_id')->all();
        $requests = $filtered()->whereIn('student_id', $ids)->orderBy('created_at')->orderBy('id')->get()->groupBy('student_id');
        $students = Student::query()->whereIn('id', $ids)->with(['studentRecords' => fn ($r) => $r->currentActive()->with('establishment:id,name')])
            ->get(['id', 'uuid', 'last_name', 'first_names', 'birth_date'])->keyBy('id');
        $requestCount = (int) $filtered()->count();

        return Inertia::render('corrections/index', [
            'students' => $groups->through(function (StudentCorrectionRequest $group) use ($requests, $students): array {
                $student = $students->get($group->student_id);
                $items = $requests->get($group->student_id, collect());

                return [
                    'uuid' => $student?->uuid, 'total' => (int) $group->getAttribute('total'), 'first_at' => Carbon::parse((string) $group->getAttribute('first_at'))->toIso8601String(),
                    'student' => $student === null ? null : [...$student->only(['last_name', 'first_names']), 'birth_date' => $student->birth_date?->format('Y-m-d')],
                    'establishments' => $student?->studentRecords->pluck('establishment.name')->unique()->values() ?? [],
                    'fields' => $items->map(fn (StudentCorrectionRequest $c) => ['label' => $c->field->label(), 'status' => $c->status->value])->values(),
                    'statuses' => $items->countBy(fn (StudentCorrectionRequest $c) => $c->status->value),
                ];
            }),
            'requestCount' => $requestCount,
            'filters' => ['status' => $status, ...$request->only(['search', 'field', 'establishment_id'])],
            'options' => [
                'statuses' => collect(StudentCorrectionStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
                'fields' => collect(StudentCorrectionField::cases())->map(fn ($f) => ['value' => $f->value, 'label' => $f->label()]),
                'establishments' => $user->hasRole('establishment') ? [] : Establishment::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /** Every request of one student in the account's perimeter, pending first, each reviewable on the spot. */
    public function student(Request $request, Student $student): Response
    {
        Gate::authorize('viewAny', StudentCorrectionRequest::class);
        $corrections = StudentCorrectionRequest::query()->visibleTo($request->user())->where('student_id', $student->id)->with('reviewer:id,name')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StudentCorrectionStatus::Pending->value])->orderByDesc('created_at')->orderByDesc('id')->get();
        abort_if($corrections->isEmpty(), 404);
        $student->load(['studentRecords' => fn ($r) => $r->currentActive()->with('establishment:id,name')]);

        return Inertia::render('corrections/student', [
            'student' => [...$student->only(['uuid', 'last_name', 'first_names', 'is_active']), 'birth_date' => $student->birth_date?->format('Y-m-d'), 'establishments' => $student->studentRecords->pluck('establishment.name')->unique()->values()],
            'corrections' => $corrections->map(fn (StudentCorrectionRequest $correction) => [
                'uuid' => $correction->uuid, 'field_label' => $correction->field->label(), 'status' => $correction->status->value, 'status_label' => $correction->status->label(),
                'current_value' => $correction->field->valueOf($student), 'proposed_value' => $correction->proposed_value, 'student_message' => $correction->student_message,
                'rejection_reason' => $correction->rejection_reason, 'created_at' => $correction->created_at->toIso8601String(), 'reviewed_at' => $correction->reviewed_at?->toIso8601String(),
                'reviewer' => $correction->reviewer?->name, 'direct' => $correction->status === StudentCorrectionStatus::Approved && $correction->reviewed_by === null,
                'conflict' => $correction->status === StudentCorrectionStatus::Pending && ! hash_equals($correction->current_fingerprint, $correction->field->fingerprint($student)),
                'canReview' => $correction->status === StudentCorrectionStatus::Pending && Gate::allows('review', $correction),
            ])->values(),
            'canViewStudent' => Gate::allows('view', $student),
        ]);
    }

    /**
     * Requests of the list with its filters, in the account's perimeter.
     *
     * @return Builder<StudentCorrectionRequest>
     */
    private function filtered(CorrectionListRequest $request, string $status): Builder
    {
        $query = StudentCorrectionRequest::query()->visibleTo($request->user())->when($status !== 'all', fn ($q) => $q->where('status', $status));
        if ($request->filled('field')) {
            $query->where('field', $request->input('field'));
        }
        if ($request->filled('establishment_id')) {
            $query->whereHas('student', fn ($q) => $q->currentlyDeclaredBy($request->integer('establishment_id')));
        }
        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $query->whereHas('student', function ($q) use ($search): void {
                $q->where(function ($q) use ($search): void {
                    foreach (['last_name' => '%%%s%%', 'first_names' => '%%%s%%'] as $field => $format) {
                        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) StudentNormalization::value($field, $search));
                        $q->orWhereRaw($field." LIKE ? ESCAPE '!'", [sprintf($format, $term)]);
                    }
                });
            });
        }

        return $query;
    }

    public function show(Request $request, StudentCorrectionRequest $correction): Response
    {
        Gate::authorize('view', $correction);
        $student = $correction->student()->with(['studentRecords' => fn ($r) => $r->currentActive()->with('establishment:id,name')])->firstOrFail();

        return Inertia::render('corrections/show', ['correction' => [
            'id' => $correction->id, 'uuid' => $correction->uuid, 'field_label' => $correction->field->label(), 'status' => $correction->status->value, 'status_label' => $correction->status->label(),
            'current_value' => $correction->field->valueOf($student), 'proposed_value' => $correction->proposed_value, 'student_message' => $correction->student_message,
            'rejection_reason' => $correction->rejection_reason, 'created_at' => $correction->created_at->toIso8601String(), 'reviewed_at' => $correction->reviewed_at?->toIso8601String(),
            'reviewer' => $correction->reviewer?->name, 'conflict' => $correction->status === StudentCorrectionStatus::Pending && ! hash_equals($correction->current_fingerprint, $correction->field->fingerprint($student)),
            'student' => [...$student->only(['last_name', 'first_names', 'is_active']), 'birth_date' => $student->birth_date?->format('Y-m-d'), 'uuid' => $student->uuid, 'establishments' => $student->studentRecords->pluck('establishment.name')->unique()->values()],
            // Sex, nationality or address saved by the student without validation (ADR-089).
            'direct' => $correction->status === StudentCorrectionStatus::Approved && $correction->reviewed_by === null,
        ], 'canReview' => $correction->status === StudentCorrectionStatus::Pending && Gate::allows('review', $correction), 'canViewStudent' => Gate::allows('view', $student),
            // Other pending requests of the same student, all reviewable on the student's page (ADR-090).
            'otherPending' => StudentCorrectionRequest::query()->visibleTo($request->user())->where('student_id', $student->id)->whereKeyNot($correction->id)->where('status', StudentCorrectionStatus::Pending->value)->count()]);
    }

    public function review(ReviewCorrectionRequest $request, StudentCorrectionRequest $correction, ReviewStudentCorrection $action): RedirectResponse
    {
        $approve = $request->validated('decision') === 'approve';
        $action->handle($request->user(), $correction, $approve, $request->validated('rejection_reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => $approve ? 'Correction approuvée et appliquée à la fiche.' : 'Demande refusée.']);

        // Decided from the student's page: the agent stays there for the next request.
        return $request->boolean('from_student')
            ? to_route('corrections.students.show', $correction->student()->firstOrFail())
            : to_route('corrections.show', $correction);
    }
}
