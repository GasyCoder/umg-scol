<?php

namespace App\Http\Controllers\Corrections;

use App\Actions\Corrections\ReviewStudentCorrection;
use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Corrections\CorrectionListRequest;
use App\Http\Requests\Corrections\ReviewCorrectionRequest;
use App\Models\Establishment;
use App\Models\StudentCorrectionRequest;
use App\Support\StudentNormalization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CorrectionController extends Controller
{
    public function index(CorrectionListRequest $request): Response
    {
        $user = $request->user();
        $status = $request->string('status')->toString() ?: StudentCorrectionStatus::Pending->value;
        $query = StudentCorrectionRequest::query()->visibleTo($user)->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['student' => fn ($q) => $q->select('id', 'student_number', 'last_name', 'first_names')->with(['studentRecords' => fn ($r) => $r->currentActive()->with('establishment:id,name')])]);
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
                    foreach (['student_number' => '%s%%', 'last_name' => '%%%s%%', 'first_names' => '%%%s%%'] as $field => $format) {
                        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) StudentNormalization::value($field, $search));
                        $q->orWhereRaw($field." LIKE ? ESCAPE '!'", [sprintf($format, $term)]);
                    }
                });
            });
        }

        return Inertia::render('corrections/index', [
            'corrections' => $query->orderBy('created_at')->orderBy('id')->paginate(20)->withQueryString()->through(fn (StudentCorrectionRequest $c) => [
                'id' => $c->id, 'field_label' => $c->field->label(), 'status' => $c->status->value, 'status_label' => $c->status->label(), 'created_at' => $c->created_at->toIso8601String(),
                'student' => $c->student->only(['last_name', 'first_names', 'student_number']), 'establishments' => $c->student->studentRecords->pluck('establishment.name')->unique()->values(),
            ]),
            'filters' => ['status' => $status, ...$request->only(['search', 'field', 'establishment_id'])],
            'options' => [
                'statuses' => collect(StudentCorrectionStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
                'fields' => collect(StudentCorrectionField::cases())->map(fn ($f) => ['value' => $f->value, 'label' => $f->label()]),
                'establishments' => $user->hasRole('establishment') ? [] : Establishment::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function show(Request $request, StudentCorrectionRequest $correction): Response
    {
        Gate::authorize('view', $correction);
        $student = $correction->student()->with(['studentRecords' => fn ($r) => $r->currentActive()->with('establishment:id,name')])->firstOrFail();

        return Inertia::render('corrections/show', ['correction' => [
            'id' => $correction->id, 'field_label' => $correction->field->label(), 'status' => $correction->status->value, 'status_label' => $correction->status->label(),
            'current_value' => $correction->field->valueOf($student), 'proposed_value' => $correction->proposed_value, 'student_message' => $correction->student_message,
            'rejection_reason' => $correction->rejection_reason, 'created_at' => $correction->created_at->toIso8601String(), 'reviewed_at' => $correction->reviewed_at?->toIso8601String(),
            'reviewer' => $correction->reviewer?->name, 'conflict' => $correction->status === StudentCorrectionStatus::Pending && ! hash_equals($correction->current_fingerprint, $correction->field->fingerprint($student)),
            'student' => [...$student->only(['last_name', 'first_names', 'student_number', 'is_active']), 'id' => $student->id, 'establishments' => $student->studentRecords->pluck('establishment.name')->unique()->values()],
        ], 'canReview' => $correction->status === StudentCorrectionStatus::Pending && Gate::allows('review', $correction), 'canViewStudent' => Gate::allows('view', $student)]);
    }

    public function review(ReviewCorrectionRequest $request, StudentCorrectionRequest $correction, ReviewStudentCorrection $action): RedirectResponse
    {
        $approve = $request->validated('decision') === 'approve';
        $action->handle($request->user(), $correction, $approve, $request->validated('rejection_reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => $approve ? 'Correction approuvée et appliquée à la fiche.' : 'Demande refusée.']);

        return to_route('corrections.show', $correction);
    }
}
