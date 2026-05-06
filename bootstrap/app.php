<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Stancl\Tenancy\Exceptions\NotASubdomainException;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global CORS – must run before everything else
        $middleware->prepend(CorsMiddleware::class);

        // Tenancy must initialize before access-prevention check
        $middleware->priority([
            InitializeTenancyBySubdomain::class,
            PreventAccessFromCentralDomains::class,
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            // Override default auth redirect behavior for API (avoid Route [login] not defined)
            'auth' => Authenticate::class,
            'ensure.superadmin' => EnsureSuperAdmin::class,
            'ensure.tenant' => EnsureTenantUser::class,

            // Spatie Laravel Permission
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TenantCouldNotBeIdentifiedOnDomainException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // When a bearer token is present on a non-superadmin tenant endpoint,
            // the caller is most likely a SuperAdmin whose dashboard requests tenant
            // resource APIs from the central domain (e.g. Navbar loading vehicle stats).
            // Return empty paginated data so the UI stays silent instead of showing
            // a 404 console error.
            if ($request->bearerToken() && ! $request->is('api/v1/superadmin/*')) {
                return response()->json(['data' => []], 200);
            }

            return response()->json([
                'message' => 'Tenant not found for this domain.',
            ], 404);
        });

        $exceptions->render(function (NotASubdomainException $e, Request $request) {
            // Requests to central (superadmin) API endpoints should be allowed
            // even when no tenant subdomain is present. For tenant API calls
            // accessed through the central domain, return 404 to indicate
            // tenant not found.
            if ($request->is('api/v1/superadmin/*')
                || $request->is('api/v1/superadmin')
                || $request->is('api/v1/superadmin/auth/*')
                || $request->is('api/v1/superadmin/auth')) {
                return null; // let the request proceed as central
            }

            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Tenant not found for this domain.',
                ], 404);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') && $request->bearerToken() && tenant() !== null) {
                // Bearer token present but invalid in tenant schema (e.g. central SuperAdmin token
                // used on a tenant route). Return 403 instead of 401 to avoid triggering the
                // frontend's global logout interceptor.
                return response()->json(['message' => 'Unauthorized for this context.'], 403);
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $errorInfo = $e->errorInfo ?? [];
            $sqlState = (string) ($errorInfo[0] ?? '');
            $message = $e->getMessage();

            $missingRelation = $sqlState === '42P01' || str_contains($message, 'SQLSTATE[42P01]');
            $dbUnavailable = $sqlState === '08006' || str_contains($message, 'SQLSTATE[08006]');

            if ($missingRelation || $dbUnavailable) {
                return response()->json([
                    'message' => 'Tenant database is not initialized yet. Please contact support.',
                ], 503);
            }

            return null;
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Recurso no encontrado.'], 404);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // HTTP exceptions (404, 405, etc.) should keep their proper status code
            if ($e instanceof HttpExceptionInterface) {
                return null;
            }

            return response()->json([
                'message' => 'Server Error',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        });
    })->create();
