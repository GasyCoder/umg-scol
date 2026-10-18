<?php

namespace App\Http\Controllers\StudentPortal;

use App\Actions\StudentPortal\LinkStudent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentPortal\LinkStudentRequest;
use App\Http\Requests\StudentPortal\VerifyBirthDateRequest;
use App\Models\Student;
use App\Support\StudentConfirmationContext;
use App\Support\StudentIdentityQuestions;
use App\Support\StudentPortalIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    private const LOCKED = 'Trop d’essais. La vérification est bloquée pour quelques heures. Réessayez plus tard ou contactez la scolarité de votre établissement.';

    public function status(Request $request): JsonResponse
    {
        $linked = $request->user()->student_id !== null && $request->session()->get('student_identity_verified') === $request->user()->student_id;

        return response()->json(['linked' => $linked, 'status' => $linked ? 'linked' : 'verification_required']);
    }

    public function verifyBirth(VerifyBirthDateRequest $request): JsonResponse
    {
        $request->session()->forget(['student_birth_verified', 'student_identity_verified']);
        if (StudentIdentityQuestions::locked($request->user())) {
            throw ValidationException::withMessages(['birth_date' => self::LOCKED]);
        }
        $student = StudentPortalIdentity::forAccount($request->user());
        if ($student === null || $student->birth_date?->format('Y-m-d') !== $request->validated('birth_date')) {
            throw ValidationException::withMessages(['birth_date' => 'Cette date ne correspond pas à votre fiche. Vérifiez votre saisie ou contactez la scolarité de votre établissement.']);
        }
        // No birth date or document number is exposed or stored in the session.
        $request->session()->put('student_birth_verified', ['student_id' => $student->id, 'verified_at' => now()->timestamp]);

        return response()->json(['verified' => true, 'questions' => StudentIdentityQuestions::issue($request->user(), $student), 'remaining' => StudentIdentityQuestions::remaining($request->user())]);
    }

    public function link(LinkStudentRequest $request, LinkStudent $action): JsonResponse
    {
        $proof = $request->session()->get('student_birth_verified');
        $student = StudentPortalIdentity::forAccount($request->user());
        if (! is_array($proof) || ($proof['verified_at'] ?? 0) < now()->subMinutes(10)->timestamp) {
            throw ValidationException::withMessages(['birth_date' => 'Vérifiez votre date de naissance avant de continuer. Cette vérification est valable 10 minutes.']);
        }
        if ($student !== null && ! StudentIdentityQuestions::pending($request->user(), $student)) {
            throw ValidationException::withMessages(['birth_date' => 'Vérifiez votre date de naissance avant de continuer.']);
        }
        if ($student !== null && ($proof['student_id'] ?? null) === $student->id) {
            $linked = $action->handle($request->user(), $request->validated('answers'), $request->validated('birth_date'));
        } else {
            // Counted like any other refusal: the remaining attempts never reveal the cause.
            StudentIdentityQuestions::fail($request->user());
            $linked = false;
        }
        if ($linked) {
            $request->session()->put('student_identity_verified', $student->id);
            $request->session()->forget('student_birth_verified');
            $request->user()->refresh();

            return response()->json(['linked' => true, 'message' => 'Votre identité est vérifiée. Bienvenue dans votre espace étudiant.']);
        }
        $remaining = StudentIdentityQuestions::remaining($request->user());

        return response()->json(['linked' => false, 'remaining' => $remaining, 'message' => $remaining === 0 ? self::LOCKED
            : 'Une réponse ne correspond pas à votre fiche. Il vous reste '.$remaining.' '.($remaining > 1 ? 'essais' : 'essai').'.']);
    }

    public function me(Request $request): JsonResponse
    {
        $student = Student::query()->whereKey($request->user()->student_id)->where('is_active', true)->first();
        abort_if($student === null, 403, 'Le compte ne permet pas actuellement de consulter une fiche.');
        // Same loaded records for the display and the confirmation state: the version token matches what is shown.
        $student->load(['studentRecords' => fn ($query) => $query->with(StudentConfirmationContext::recordRelations())]);
        $data = $student->only(['last_name', 'first_names', 'mother_name', 'nationality', 'phone', 'email', 'address', 'bac_series', 'bac_year', 'scholarship_code']);
        $data['sex'] = $student->sex?->value;
        $data['birth_date'] = $student->birth_date?->format('Y-m-d');
        $data['records'] = $student->studentRecords->map(fn ($record) => ['academic_year' => $record->academicYear->name, 'establishment' => $record->establishment->name, 'level' => $record->level->name, 'mention' => $record->program?->mention?->name, 'program' => $record->program?->name, 'repeat_code' => $record->repeat_code?->label(), 'status' => $record->status->value])->values();

        return response()->json(['student' => $data, 'confirmation' => StudentConfirmationContext::load($student)->forStudent()]);
    }
}
