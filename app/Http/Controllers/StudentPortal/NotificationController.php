<?php

namespace App\Http\Controllers\StudentPortal;

use App\Enums\StudentCorrectionField;
use App\Http\Controllers\Controller;
use App\Models\StudentInformationRequest;
use App\Support\StudentPortalNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bell of the student portal and the scholarship office's messages of the « Mes demandes » page (ADR-089). */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(StudentPortalNotifications::for($request->user()));
    }

    public function seen(Request $request): JsonResponse
    {
        StudentPortalNotifications::markSeen($request->user());

        return response()->json(['unread' => 0]);
    }

    /** Emails the office sent about this record: field names and the agent's message, never a value. */
    public function messages(Request $request): JsonResponse
    {
        $messages = StudentInformationRequest::query()->where('student_id', $request->user()->student_id)->latest('id')->limit(50)->get();

        return response()->json(['messages' => $messages->map(fn (StudentInformationRequest $message) => [
            'id' => $message->id, 'at' => $message->created_at->toIso8601String(), 'message' => $message->message,
            'fields' => array_map(fn (string $field) => StudentCorrectionField::from($field)->label(), $message->fields),
        ])->values()]);
    }
}
