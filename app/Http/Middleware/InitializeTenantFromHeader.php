<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class InitializeTenantFromHeader
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (! $tenantId) {
            return $next($request);
        }

        try {
            // Validate tenant ID format before querying
            if (! $this->isValidTenantId($tenantId)) {
                return response()->json(['message' => 'Invalid Tenant ID format'], 400);
            }

            $tenant = Tenant::findOrFail((string) $tenantId);
            tenancy()->initialize($tenant);

            // Use validated ID for schema name construction
            $schemaName = 'tenant_'.(int) $tenant->id;
            \DB::statement("SET search_path TO \"$schemaName\", public");

            return $next($request);

        } catch (\Exception $e) {
            \Log::error('Failed to initialize tenant from header', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Tenant not found'], 404);
        }
    }

    private function isValidTenantId($tenantId): bool
    {
        return is_numeric($tenantId) && (int) $tenantId > 0 && preg_match('/^\d+$/', $tenantId);
    }
}
