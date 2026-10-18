<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->student_id !== null && $request->session()->get('student_identity_verified') === $user->student_id, 403, 'Vérifiez votre identité avant de consulter votre fiche.');
        abort_unless(Student::query()->whereKey($user->student_id)->where('is_active', true)->exists(), 403, 'Votre fiche ne permet plus cet accès. Contactez votre établissement.');

        return $next($request);
    }
}
