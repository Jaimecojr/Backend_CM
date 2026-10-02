<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects any authenticated user that is not a super admin.
 *
 * Hiding a screen in the panel is not access control: a franchise user can call the API directly,
 * so every super-admin-only route must be guarded on the server with this middleware.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->isSuperAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return $next($request);
    }
}
