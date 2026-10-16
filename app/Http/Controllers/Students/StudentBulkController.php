<?php

namespace App\Http\Controllers\Students;

use App\Actions\Students\BulkStudentRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\BulkStudentRecordsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StudentBulkController extends Controller
{
    public function __invoke(BulkStudentRecordsRequest $request, BulkStudentRecords $action): RedirectResponse
    {
        $verb = $request->validated('action');
        $result = $action->handle($request->user(), $verb, $request->validated('records'));
        $done = match ($verb) {
            'activate' => 'réactivée(s)',
            'deactivate' => 'désactivée(s)',
            default => 'étudiant(s) déplacé(s) dans la corbeille',
        };
        $parts = [$verb === 'trash' ? $result['done'].' '.$done : $result['done'].' situation(s) '.$done];
        if ($result['unchanged'] > 0) {
            $parts[] = $result['unchanged'].' déjà dans cet état';
        }
        if ($result['refused'] > 0) {
            $parts[] = $result['refused'].' refusée(s) (droits ou contrôles)';
        }
        Inertia::flash('toast', ['type' => $result['refused'] > 0 ? 'warning' : 'success', 'message' => implode(' · ', $parts).'.']);

        return back();
    }
}
