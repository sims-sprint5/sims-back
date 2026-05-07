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
        // Prioridad 1: extraer slug del subdominio y buscar por ID en BD.
        // aaa.jilsoftwares.deltahost.asix2.iesmontsia.cat → slug = "aaa" → Tenant::find("aaa")
        // Así cualquier tenant creado con ese ID funciona automáticamente en su subdominio
        // sin necesidad de registrar el dominio en la tabla domains.
        $slug = $this->extractSlugFromHost($request->getHost());

        if ($slug !== null) {
            $tenant = Tenant::find($slug);

            if ($tenant) {
                $this->tenancy->initialize($tenant);

                return $next($request);
            }
        }

        // Prioridad 2: búsqueda exacta en tabla domains (comportamiento original)
        return $this->initializeTenancy($request, $next, $request->getHost());
    }

    private function extractSlugFromHost(string $host): ?string
    {
        $baseDomain = (string) env('TENANT_BASE_DOMAIN', '');

        if ($baseDomain === '') {
            return null;
        }

        $escaped = preg_quote($baseDomain, '/');

        if (preg_match('/^([a-z0-9][a-z0-9\-]*)\.'.$escaped.'$/i', $host, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
