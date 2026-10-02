<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defensive HTTP headers to every response the application produces.
 *
 * The API only returns JSON and file downloads, so it never needs to be framed, sniffed as another
 * content type or load sub-resources — the restrictive CSP below is safe here. Files under
 * /storage are served directly by the web server and do not pass through this middleware.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        $headers->remove('X-Powered-By');

        // HSTS only makes sense over HTTPS; sending it from a local http:// server is ignored anyway,
        // but limiting it to production avoids pinning developer machines to HTTPS by accident.
        if (app()->isProduction() && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }

        return $response;
    }
}
