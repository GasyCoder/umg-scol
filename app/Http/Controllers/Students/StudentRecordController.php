<?php

namespace App\Http\Controllers\Students;

use App\Actions\Students\SaveStudentRecord;
use App\Actions\Students\VerifyStudentRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\SaveStudentRecordRequest;
use App\Http\Requests\Students\StudentStatusRequest;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Support\StudentReferenceOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentRecordController extends Controller
{
    public function create(Request $request, Student $student): Response
    {
        Gate::authorize('create', [StudentRecord::class, $student]);

        return Inertia::render('students/record-form', ['student' => $student->only(['id', 'uuid', 'last_name', 'first_names']), 'record' => null, 'options' => app(StudentReferenceOptions::class)->forUser($request->user())]);
    }

    public function store(SaveStudentRecordRequest $request, Student $student): RedirectResponse
    {
        app(SaveStudentRecord::class)->handle($request->user(), $student, null, $request->validated());

        return to_route('students.show', $student);
    }

    public function edit(Request $request, StudentRecord $student_record): Response
    {
        Gate::authorize('update', $student_record);

        return Inertia::render('students/record-form', ['student' => $student_record->student->only(['id', 'uuid', 'last_name', 'first_names']), 'record' => $student_record, 'options' => app(StudentReferenceOptions::class)->forUser($request->user())]);
    }

    public function update(SaveStudentRecordRequest $request, StudentRecord $student_record): RedirectResponse
    {
        app(SaveStudentRecord::class)->handle($request->user(), $student_record->student, $student_record, $request->validated());

        return to_route('students.show', $student_record->student);
    }

    public function status(StudentStatusRequest $request, StudentRecord $student_record): RedirectResponse
    {
        app(SaveStudentRecord::class)->handle($request->user(), $student_record->student, $student_record, $request->validated());

        return to_route('students.show', $student_record->student);
    }

    /** The central office validates a situation it entered, once found in the establishment's list. */
    public function verify(Request $request, StudentRecord $student_record, VerifyStudentRecord $action): RedirectResponse
    {
        $action->handle($request->user(), $student_record);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Situation validée.']);

        return to_route('students.show', $student_record->student);
    }
}
