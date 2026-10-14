<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $portal = $host === config('portals.scol_domain') ? 'scol' : ($host === config('portals.bourse_domain') ? 'bourse' : null);
        abort_if($portal === null, 404);
        abort_if($portal === 'bourse' && $request->header('Authorization') !== null, 401);
        if (! $request->isMethodSafe() && $request->headers->has('Origin')) {
            abort_if($request->header('Origin') !== $request->getSchemeAndHttpHost(), 419);
        }
        $previous = config('session.cookie');
        config(['session.cookie' => config('portals.cookie_prefix').'-'.$portal.'-session', 'session.domain' => null, 'session.http_only' => true, 'session.same_site' => 'lax']);
        app('session')->driver()->setName(config('session.cookie'));
        try {
            $response = $next($request);
            if ($portal === 'bourse') {
                $response->headers->set('Cache-Control', 'private, no-store');
            }

            return $response;
        } finally {
            config(['session.cookie' => $previous]);
        }
    }
}
