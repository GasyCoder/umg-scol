<?php

namespace App\Http\Controllers\StudentPortal;

use App\Actions\StudentPortal\AuthenticateGoogleStudent;
use App\Actions\StudentPortal\RequestEmailOtp;
use App\Actions\StudentPortal\VerifyEmailOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentPortal\RequestOtpRequest;
use App\Http\Requests\StudentPortal\VerifyOtpRequest;
use App\Models\User;
use App\Support\StudentPortalAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

class AuthenticationController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        $user = Auth::guard('student')->user();
        if ($user !== null) {
            abort_unless(StudentPortalAccess::student($user) && $user->hasVerifiedEmail() && $request->session()->get('student_portal') === true, 403);
        }

        return response()->json(['authenticated' => $user !== null, 'linked' => $user?->student_id !== null && $request->session()->get('student_identity_verified') === $user->student_id, 'csrf_token' => csrf_token(), 'google_enabled' => $this->googleEnabled(), 'email' => $user?->email]);
    }

    public function requestOtp(RequestOtpRequest $request, RequestEmailOtp $action): JsonResponse
    {
        if (! $request->session()->has('otp_binding')) {
            $request->session()->put('otp_binding', Str::random(64));
        }
        $id = $action->handle($request->validated('email'), $request->session()->get('otp_binding'));

        return response()->json(['challenge_id' => $id, 'message' => 'Un code de vérification a été envoyé à votre adresse email.']);
    }

    public function verifyOtp(VerifyOtpRequest $request, VerifyEmailOtp $action): JsonResponse
    {
        $user = $action->handle($request->validated('challenge_id'), $request->validated('code'), (string) $request->session()->get('otp_binding', ''));
        $this->login($request, $user, 'otp_login');

        return $this->session($request);
    }

    private function googleEnabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'));
    }

    private function login(Request $request, User $user, string $event): void
    {
        Auth::guard('student')->login($user);
        $request->session()->regenerate();
        $request->session()->forget('otp_binding');
        $request->session()->forget(['student_identity_verified', 'student_birth_verified']);
        $request->session()->put('student_portal', true);
        StudentPortalAccess::audit($user, $event);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnexion effectuée.', 'csrf_token' => csrf_token()]);
    }

    public function googleRedirect(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        abort_unless($this->googleEnabled(), 503);
        $request->session()->put('google_link_user', $request->session()->get('student_portal') === true ? Auth::guard('student')->id() : null);

        return Socialite::driver('google')->redirect();
    }

    public function googleCallback(Request $request, AuthenticateGoogleStudent $action): RedirectResponse
    {
        try {
            $profile = Socialite::driver('google')->user();
            if (! $profile instanceof GoogleUser) {
                throw new \RuntimeException;
            }
            $current = Auth::guard('student')->user();
            if ($request->session()->pull('google_link_user') !== $current?->getAuthIdentifier()) {
                throw new \RuntimeException;
            }
            $user = $action->handle($profile, $current instanceof User ? $current : null);
            $this->login($request, $user, 'google_login');

            return redirect('/');
        } catch (\Throwable) {
            return redirect('/?auth_error=google');
        }
    }
}
