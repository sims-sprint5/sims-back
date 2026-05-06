<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated user is a tenant User (not a SuperAdmin or other model).
 *
 * This prevents Sanctum tokens issued to central SuperAdmins from being
 * accepted on tenant routes, closing the cross-schema token leak.
 */
class EnsureTenantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User) {
            // Return 403 (not 401) so the frontend's global 401-logout interceptor
            // is not triggered when a SuperAdmin token is used on a tenant route.
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return $next($request);
    }
}
