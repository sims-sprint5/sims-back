<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\IdentificationMiddleware;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByDomainOrHeader extends IdentificationMiddleware
{
    /** @var callable|null */
    public static $onFail;

    public function __construct(Tenancy $tenancy, DomainTenantResolver $resolver)
    {
        $this->tenancy = $tenancy;
        $this->resolver = $resolver;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Priority 1: X-Tenant header allows API calls from any domain
        if ($request->hasHeader('X-Tenant')) {
            $tenantId = $request->header('X-Tenant');
            $tenant = Tenant::find($tenantId);

            if (! $tenant) {
                return response()->json(['message' => 'Tenant not found.'], 404);
            }

            $this->tenancy->initialize($tenant);

            return $next($request);
        }

        // Priority 2: Domain lookup (subdomain-based access)
        return $this->initializeTenancy($request, $next, $request->getHost());
    }
}
