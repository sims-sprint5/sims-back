<?php

namespace App\Http\Middleware;

use App\Models\SuperAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Only allow requests whose Sanctum token belongs to a SuperAdmin model.
     * Must be used after auth:sanctum has already resolved the user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! ($request->user() instanceof SuperAdmin)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
