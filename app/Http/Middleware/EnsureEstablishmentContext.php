<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEstablishmentContext
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()->hasUsableEstablishment(), 403, 'Compte établissement sans rattachement actif.');

        return $next($request);
    }
}
