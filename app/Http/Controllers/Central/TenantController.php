<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
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

        try {
            $this->safeLog('info', "Running tenant migrations for '{$tenantId}'...");
            Artisan::call('tenants:migrate', [
                '--tenants' => [$tenantId],
                '--force' => true,
            ]);
            $migrateOutput = Artisan::output();

            $this->safeLog('info', "Running tenant seeder for '{$tenantId}'...");
            Artisan::call('tenants:seed', [
                '--tenants' => [$tenantId],
                '--force' => true,
            ]);
            $seedOutput = Artisan::output();

            $this->safeLog('info', "Tenant database initialized for '{$tenantId}'", [
                'migrate_output' => $migrateOutput,
                'seed_output' => $seedOutput,
            ]);
        } catch (\Throwable $e) {
            $this->safeLog('error', "Tenant bootstrap failed for '{$tenantId}': ".$e->getMessage());
            $this->safeLog('error', $e->getTraceAsString());
            $tenant->delete();

            return response()->json([
                'message' => 'Tenant creation failed during migrate/seed.',
                'error' => $e->getMessage(),
            ], 500);
        }

        // Generate SSL certificate synchronously using Name.com DNS-01 script
        $domain = "{$tenantId}.{$baseDomain}";
        $sslScriptPath = base_path((string) env('SSL_DNS_SCRIPT_PATH', 'scripts/namecom_certbot_dns01.py'));

        try {
            $this->safeLog('info', "Generating SSL certificate for {$domain} using DNS-01 script...");

            if (! file_exists($sslScriptPath)) {
                throw new \RuntimeException("SSL script not found at: {$sslScriptPath}");
            }

            $command = sprintf(
                'python3 %s --mode issue --domain %s --subdomain %s 2>&1',
                escapeshellarg($sslScriptPath),
                escapeshellarg($baseDomain),
                escapeshellarg($tenantId)
            );
            $output = shell_exec($command);

            $this->safeLog('info', "SSL certificate command executed for {$domain}", [
                'script' => $sslScriptPath,
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            $this->safeLog('warning', "SSL generation warning for {$domain}: ".$e->getMessage());
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

    public function update(string $id, Request $request)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'admin_name' => 'nullable|string|max:255',
            'admin_email' => 'nullable|email|max:255',
            'admin_password' => 'nullable|string|min:8',
        ]);

        $tenant = Tenant::with('domains')->findOrFail($id);

        $data = $request->only(['name', 'admin_name', 'admin_email']);

        $tenant->update($data);

        if ($request->filled('admin_password')) {
            $tenant->update(['admin_password' => $request->admin_password]);

            tenancy()->initialize($tenant);

            $admin = User::where('email', $tenant->admin_email)->first();
            if ($admin) {
                $admin->password = Hash::make($request->admin_password);
                $admin->save();
            }

            tenancy()->end();

            $tenant->update(['admin_password' => null]);
        }

        return response()->json([
            'message' => 'Tenant updated successfully',
            'tenant' => $tenant,
        ]);
    }

    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();

        return response()->json(['message' => "Tenant '{$id}' deleted successfully."]);
    }

    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::{$level}($message, $context);
        } catch (\Throwable $e) {
        }
    }
}
