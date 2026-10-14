<?php

namespace App\Http\Controllers\StudentPortal;

use App\Actions\StudentPortal\ReviewStudentLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentPortal\ReviewLinkRequest;
use App\Models\StudentLinkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LinkReviewController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StudentLinkRequest::class);

        return Inertia::render('student-links/index', ['requests' => StudentLinkRequest::query()->visibleTo($request->user())->where('status', 'pending')->with(['user:id,name,email', 'student:id,last_name,first_names,student_number'])->latest('id')->paginate(20)->through(fn ($row) => ['id' => $row->id, 'uuid' => $row->uuid, 'requested_at' => $row->requested_at, 'user' => $row->user->only(['name', 'email']), 'student' => $row->student->only(['last_name', 'first_names', 'student_number'])])]);
    }

    public function review(ReviewLinkRequest $request, StudentLinkRequest $studentLinkRequest, ReviewStudentLink $action): RedirectResponse
    {
        $action->handle($request->user(), $studentLinkRequest, $request->validated('decision') === 'approve', $request->validated('rejection_reason'));

        return to_route('student-links.index');
    }
}
