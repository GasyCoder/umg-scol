<?php

namespace App\Http\Middleware;

use App\Support\StudentPortalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->getHost() === config('portals.bourse_domain'), 404);
        $user = $request->user();
        abort_unless($user !== null && StudentPortalAccess::student($user) && $user->hasVerifiedEmail(), 403);
        abort_unless($request->session()->get('student_portal') === true, 403);

        return $next($request);
    }
}
