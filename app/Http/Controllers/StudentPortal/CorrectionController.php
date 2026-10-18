<?php

namespace App\Http\Controllers\StudentPortal;

use App\Actions\Corrections\CancelStudentCorrection;
use App\Actions\Corrections\RequestStudentCorrection;
use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentPortal\StoreCorrectionRequest;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->student_id === null || ! Student::query()->whereKey($user->student_id)->exists(), 403);
        $corrections = StudentCorrectionRequest::query()->where('user_id', $user->id)->where('student_id', $user->student_id)->latest('id')->limit(100)->get();

        return response()->json(['corrections' => $corrections->map(fn (StudentCorrectionRequest $correction) => $this->present($correction))->values()]);
    }

    public function store(StoreCorrectionRequest $request, RequestStudentCorrection $action): JsonResponse
    {
        $correction = $action->handle($request->user(), StudentCorrectionField::from($request->validated('field')), $request->validated('value'), $request->validated('message'));

        $message = $correction->field->appliesDirectly()
            ? $correction->field->label().' mis à jour.'
            : 'Votre demande a été transmise. Les informations officielles ne changeront qu’après validation par la scolarité.';

        return response()->json(['correction' => $this->present($correction), 'message' => $message], 201);
    }

    public function cancel(Request $request, string $correction, CancelStudentCorrection $action): JsonResponse
    {
        $user = $request->user();
        abort_if($user->student_id === null || ! Student::query()->whereKey($user->student_id)->exists(), 403);
        // Scoped lookup: another student's request is indistinguishable from a missing one.
        $target = StudentCorrectionRequest::query()->where('user_id', $user->id)->where('student_id', $user->student_id)->where('uuid', $correction)->firstOrFail();

        return response()->json(['correction' => $this->present($action->handle($user, $target)), 'message' => 'Demande annulée.']);
    }

    /** @return array<string,mixed> */
    private function present(StudentCorrectionRequest $correction): array
    {
        $value = $correction->proposed_value;

        return [
            'uuid' => $correction->uuid, 'field' => $correction->field->value, 'field_label' => $correction->field->label(),
            'proposed_value' => $correction->field === StudentCorrectionField::Cin ? str_repeat('•', max(0, mb_strlen($value) - 3)).mb_substr($value, -3) : $value,
            // Phone or address saved at once (ADR-089): approved without reviewer.
            'direct' => $correction->status === StudentCorrectionStatus::Approved && $correction->reviewed_by === null,
            'status' => $correction->status->value, 'status_label' => $correction->status === StudentCorrectionStatus::Approved && $correction->reviewed_by === null ? 'Enregistrée' : $correction->status->label(), 'student_message' => $correction->student_message,
            'decision_reason' => $correction->rejection_reason, 'created_at' => $correction->created_at->toIso8601String(), 'reviewed_at' => $correction->reviewed_at?->toIso8601String(),
        ];
    }
}
