<?php

namespace App\Http\Controllers\StudentPortal;

use App\Actions\StudentPortal\ConfirmStudentInformation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentPortal\ConfirmInformationRequest;
use App\Models\Student;
use App\Support\StudentConfirmationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfirmationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $student = Student::query()->whereKey($request->user()->student_id)->where('is_active', true)->first();
        abort_if($student === null, 403, 'Le compte ne permet pas actuellement de consulter une fiche.');

        return response()->json(['confirmation' => StudentConfirmationContext::load($student)->forStudent()]);
    }

    public function store(ConfirmInformationRequest $request, ConfirmStudentInformation $action): JsonResponse
    {
        $context = $action->handle($request->user(), $request->validated('version'));

        return response()->json(['confirmation' => $context->forStudent(), 'message' => 'Merci : la confirmation de vos informations est enregistrée.']);
    }
}
