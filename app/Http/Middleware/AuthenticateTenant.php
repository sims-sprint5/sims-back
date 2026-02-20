<?php

namespace App\Http\Middleware;

use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthenticateTenant
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->unauthorized();
        }

        $tenantId = $request->header('X-Tenant-ID');
        if (! $this->isValidTenantId($tenantId)) {
            return response()->json(['message' => 'Invalid Tenant ID.'], 400);
        }

        try {
            $user = $this->authenticateToken($token, $tenantId);
            if (! $user) {
                return $this->unauthorized();
            }

            auth('tenant')->setUser($user);
            auth()->setUser($user);

            return $next($request);
        } catch (\Exception $e) {
            return $this->unauthorized();
        }
    }

    private function isValidTenantId($tenantId): bool
    {
        return is_numeric($tenantId) && (int) $tenantId > 0 && preg_match('/^\d+$/', $tenantId);
    }

    private function authenticateToken(string $token, string $tenantId): ?User
    {
        [$tokenId, $tokenSecret] = explode('|', $token, 2);
        $hashedToken = hash('sha256', $tokenSecret);
        $schema = 'tenant_'.(int) $tenantId;

        $personalAccessToken = DB::connection('pgsql')
            ->table("{$schema}.personal_access_tokens")
            ->where('id', $tokenId)
            ->where('token', $hashedToken)
            ->first();

        if (! $personalAccessToken) {
            return null;
        }

        $userData = DB::connection('pgsql')
            ->table("{$schema}.users")
            ->where('id', $personalAccessToken->tokenable_id)
            ->first();

        if (! $userData) {
            return null;
        }

        $user = User::find($userData->id);
        if (! $user) {
            return null;
        }

        $user->load('roles', 'permissions');

        return $user;
    }

    private function unauthorized()
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
