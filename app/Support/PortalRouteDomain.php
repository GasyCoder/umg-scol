<?php

namespace App\Support;

/**
 * Domain bound to a portal's routes. Wayfinder copies a route domain into the generated
 * front-end URLs without any port (//scol.umg.test/login), which sends every form and link
 * of the documented local setup (port 8000) to another origin. While those URLs are being
 * generated, routes are left unbound so that they stay relative to the current portal.
 * Host routing at runtime is unchanged: requests are always matched against the domain.
 * Only the internal portal uses it: the bourse SPA calls its API with relative paths.
 */
final class PortalRouteDomain
{
    public static function for(string $domain): ?string
    {
        return app()->runningConsoleCommand('wayfinder:generate') ? null : $domain;
    }
}
