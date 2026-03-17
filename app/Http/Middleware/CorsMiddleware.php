<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('cors');
        $origin = $request->header('Origin');

        // Check if origin is allowed
        if (! $this->isOriginAllowed($origin, $config)) {
            // If not a preflight request, continue without CORS headers
            if ($request->method() !== 'OPTIONS') {
                return $next($request);
            }
            // Otherwise deny preflight
            return response('', 403);
        }

        // Handle preflight requests
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflightRequest($origin, $config);
        }

        // Add CORS headers to response
        return $next($request)
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', implode(', ', $config['allowed_methods']))
            ->header('Access-Control-Allow-Headers', implode(', ', $config['allowed_headers']))
            ->header('Access-Control-Max-Age', $config['max_age'])
            ->header('Access-Control-Allow-Credentials', $config['supports_credentials'] ? 'true' : 'false');
    }

    /**
     * Handle preflight request.
     */
    protected function handlePreflightRequest(string $origin, array $config): Response
    {
        return response('', 204)
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', implode(', ', $config['allowed_methods']))
            ->header('Access-Control-Allow-Headers', implode(', ', $config['allowed_headers']))
            ->header('Access-Control-Max-Age', $config['max_age'])
            ->header('Access-Control-Allow-Credentials', $config['supports_credentials'] ? 'true' : 'false');
    }

    /**
     * Check if the origin is allowed.
     */
    protected function isOriginAllowed(?string $origin, array $config): bool
    {
        if (! $origin) {
            return false;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);

        // Check against exact origins
        foreach ($config['allowed_origins'] as $allowedOrigin) {
            if ($origin === $allowedOrigin) {
                return true;
            }
        }

        // Check against regex patterns
        foreach ($config['allowed_origins_patterns'] as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        $tenantBaseDomain = trim((string) env('TENANT_BASE_DOMAIN', ''));
        if ($tenantBaseDomain !== '' && is_string($originHost)) {
            if ($originHost === $tenantBaseDomain || str_ends_with($originHost, '.'.$tenantBaseDomain)) {
                return true;
            }
        }

        return false;
    }
}
