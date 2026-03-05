<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Jobs\SeedDatabase;

class TenantController extends Controller
{
    public function index()
    {
        return response()->json(Tenant::with('domains')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'id'             => 'required|string|alpha_dash|unique:tenants,id|max:50',
            'name'           => 'required|string|max:255',
            'admin_name'     => 'nullable|string|max:255',
            'admin_email'    => 'nullable|email|max:255',
            'admin_password' => 'nullable|string|min:8',
        ]);

        $tenantId   = $request->id;
        $baseDomain = env('TENANT_BASE_DOMAIN', 'localhost');

        $parsed     = parse_url(config('app.url'));
        $scheme     = $parsed['scheme'] ?? 'http';
        $port       = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        $adminEmail = $request->admin_email ?? "admin@{$tenantId}.{$baseDomain}";

        $tenant = Tenant::create([
            'id'             => $tenantId,
            'name'           => $request->name,
            'admin_name'     => $request->admin_name ?? 'Admin ' . ucfirst($tenantId),
            'admin_email'    => $adminEmail,
            'admin_password' => $request->admin_password ?? '',
        ]);

        $tenant->domains()->create(['domain' => $tenantId]);

        // Run seed explicitly (isolated from schema+migrate pipeline).
        // If the seed fails, roll back the tenant creation so the DB stays
        // consistent and the caller receives a clear 500 error instead of a
        // silent partial-success.
        try {
            SeedDatabase::dispatchSync($tenant);
        } catch (\Throwable $e) {
            Log::error("Tenant seed failed for '{$tenantId}': " . $e->getMessage());
            $tenant->delete();
            return response()->json([
                'message' => 'Tenant creation failed during database seeding.',
                'error'   => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Tenant created successfully',
            'tenant'  => $tenant->load('domains'),
            'access'  => [
                'url'         => "{$scheme}://{$tenantId}.{$baseDomain}{$port}",
                'admin_email' => $adminEmail,
            ],
        ], 201);
    }

    public function show(string $id)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        return response()->json($tenant);
    }

    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();
        return response()->json(['message' => "Tenant '{$id}' deleted successfully."]);
    }
}
