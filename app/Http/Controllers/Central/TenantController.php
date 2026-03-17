<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantController extends Controller
{
    public function index()
    {
        return response()->json(Tenant::with('domains')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'id' => 'required|string|alpha_dash|unique:tenants,id|max:50',
            'name' => 'required|string|max:255',
            'admin_name' => 'nullable|string|max:255',
            'admin_email' => 'nullable|email|max:255',
            'admin_password' => 'nullable|string|min:8',
        ]);

        $tenantId = $request->id;
        $baseDomain = env('TENANT_BASE_DOMAIN', 'localhost');
        $defaultTenantAdminPassword = (string) env('TENANT_DEFAULT_ADMIN_PASSWORD', 'password123');
        $adminPassword = $request->filled('admin_password')
            ? (string) $request->admin_password
            : $defaultTenantAdminPassword;

        $parsed = parse_url(config('app.url'));
        $scheme = $parsed['scheme'] ?? 'http';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        $adminEmail = $request->admin_email ?? "admin@{$tenantId}.{$baseDomain}";

        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => $request->name,
            'admin_name' => $request->admin_name ?? 'Admin '.ucfirst($tenantId),
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
        ]);

        $tenant->domains()->create(['domain' => $tenantId]);

        // Generate SSL certificate synchronously
        $domain = "{$tenantId}.{$baseDomain}";
        $certEmail = env('CERT_EMAIL', 'admin@simsgrup2.app');

        try {
            Log::info("Generating SSL certificate for {$domain}...");

            $command = "sudo certbot --nginx -d {$domain} --non-interactive --agree-tos -m {$certEmail} 2>&1";
            $output = shell_exec($command);

            Log::info("SSL certificate generated for {$domain}");
        } catch (\Throwable $e) {
            Log::warning("SSL generation warning for {$domain}: ".$e->getMessage());
            // Don't fail tenant creation if SSL fails
        }

        return response()->json([
            'message' => 'Tenant created successfully',
            'tenant' => $tenant->load('domains'),
            'access' => [
                'url' => "{$scheme}://{$tenantId}.{$baseDomain}{$port}",
                'admin_email' => $adminEmail,
                'admin_password' => $adminPassword,
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
