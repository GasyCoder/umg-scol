<?php

namespace App\Http\Controllers\StudentPortal;

use App\Actions\StudentPortal\LinkStudent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentPortal\LinkStudentRequest;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        return response()->json(['linked' => $request->user()->student_id !== null, 'status' => $request->user()->student_id !== null ? 'linked' : 'verification_required']);
    }

    public function link(LinkStudentRequest $request, LinkStudent $action): JsonResponse
    {
        $linked = $action->handle($request->user(), $request->validated('identifier_type'), $request->validated('identifier'), $request->validated('birth_date'));

        return response()->json(['linked' => $linked, 'message' => $linked ? 'Votre compte est rattaché.' : 'Les informations fournies ne permettent pas un rattachement automatique. Une vérification peut être nécessaire.']);
    }

    public function me(Request $request): JsonResponse
    {
        $student = Student::query()->whereKey($request->user()->student_id)->where('is_active', true)->first();
        abort_if($student === null, 403, 'Le compte ne permet pas actuellement de consulter une fiche.');
        $student->load(['studentRecords.academicYear:id,name', 'studentRecords.establishment:id,name', 'studentRecords.level:id,code,name', 'studentRecords.program:id,name']);
        $data = $student->only(['student_number', 'last_name', 'first_names', 'birth_place', 'phone', 'email', 'address', 'bac_registration_number', 'bac_series', 'bac_year', 'bac_mention']);
        $data['birth_date'] = $student->birth_date?->format('Y-m-d');
        $data['records'] = $student->studentRecords->map(fn ($record) => ['academic_year' => $record->academicYear->name, 'establishment' => $record->establishment->name, 'level' => $record->level->name, 'program' => $record->program?->name, 'status' => $record->status->value])->values();

        return response()->json(['student' => $data]);
    }
}
