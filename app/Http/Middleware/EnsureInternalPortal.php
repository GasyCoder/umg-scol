<?php

namespace App\Http\Middleware;

use App\Support\StudentPortalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->getHost() === config('portals.scol_domain'), 404);
        if ($user = $request->user('web')) {
            abort_unless(StudentPortalAccess::internal($user), 403);
        }

        return $next($request);
    }
}
