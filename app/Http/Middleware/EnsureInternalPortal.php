<?php

namespace App\Http\Middleware;

use App\Support\StudentPortalAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->getHost() === config('portals.scol_domain'), 404);
        if ($user = $request->user('web')) {
            // A suspension ends the session already open, not only the next login.
            if (StudentPortalAccess::internalAccount($user) && ! $user->is_active) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return to_route('login')->with('status', 'Votre compte a été suspendu. Contactez un administrateur.');
            }
            abort_unless(StudentPortalAccess::internal($user), 403);
        }

        return $next($request);
    }
}
