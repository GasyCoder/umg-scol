<?php

namespace App\Http\Controllers\Students;

use App\Actions\Students\NotifyStudentInformation;
use App\Actions\Students\NotifyStudentsInformation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\BulkNotifyStudentsRequest;
use App\Http\Requests\Students\NotifyStudentRequest;
use App\Models\ScholarshipApplication;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Emails asking students to complete or check their record (ADR-088). */
class StudentNotificationController extends Controller
{
    public function store(NotifyStudentRequest $request, Student $student, NotifyStudentInformation $action): RedirectResponse
    {
        // Only a file of this very student is recorded with the sending.
        $application = $request->filled('application')
            ? ScholarshipApplication::query()->where('uuid', $request->validated('application'))->where('student_id', $student->id)->first()
            : null;
        $action->handle($request->user(), $student, $request->validated('fields'), $request->validated('message'), $application);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Email envoyé à l’étudiant.']);

        return back();
    }

    public function bulk(BulkNotifyStudentsRequest $request, NotifyStudentsInformation $action): RedirectResponse
    {
        $kind = $request->has('applications') ? 'applications' : ($request->has('records') ? 'records' : 'students');
        $result = $action->handle($request->user(), $kind, $request->validated($kind));
        $parts = [$result['sent'].' email(s) envoyé(s)'];
        foreach (['nothing' => 'sans information à signaler', 'no_email' => 'sans email', 'refused' => 'refusé(s) (droits ou fiche inactive)'] as $key => $label) {
            if ($result[$key] > 0) {
                $parts[] = $result[$key].' '.$label;
            }
        }
        Inertia::flash('toast', ['type' => $result['sent'] > 0 ? 'success' : 'warning', 'message' => implode(' · ', $parts).'.']);

        return back();
    }
}
