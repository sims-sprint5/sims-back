<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class TenantController extends Controller
{
    public function index()
    {
        return response()->json(Tenant::with('domains')->get());
    }

    public function store(Request $request)
    {
        if (! $this->isCentralSchemaReady()) {
            return response()->json([
                'message' => 'Central database is not initialized. Run central migrations first.',
            ], 503);
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Central database connection failed. Please verify DB host/credentials.',
                'error' => $e->getMessage(),
            ], 503);
        }

        $request->validate([
            'id' => 'required|string|alpha_dash|max:50',
            'name' => 'required|string|max:255',
            'admin_name' => 'nullable|string|max:255',
            'admin_email' => 'nullable|email|max:255',
            'admin_password' => 'nullable|string|min:8',
        ]);

        $tenantId = strtolower((string) $request->id);
        $baseDomain = env('TENANT_BASE_DOMAIN', 'localhost');
        $defaultTenantAdminPassword = (string) env('TENANT_DEFAULT_ADMIN_PASSWORD', 'password123');
        $adminPassword = $request->filled('admin_password')
            ? (string) $request->admin_password
            : $defaultTenantAdminPassword;

        $parsed = parse_url(config('app.url'));
        $scheme = $parsed['scheme'] ?? 'http';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        $adminEmail = $request->admin_email ?? "admin@{$tenantId}.{$baseDomain}";

        $tenant = Tenant::query()->find($tenantId);
        $isExistingTenant = (bool) $tenant;

        if (! $tenant) {
            $tenant = Tenant::create([
                'id' => $tenantId,
                'name' => $request->name,
                'admin_name' => $request->admin_name ?? 'Admin '.ucfirst($tenantId),
                'admin_email' => $adminEmail,
                'admin_password' => $adminPassword,
            ]);
        } else {
            $tenant->update([
                'name' => $request->name,
                'admin_name' => $request->admin_name ?? ($tenant->admin_name ?? 'Admin '.ucfirst($tenantId)),
                'admin_email' => $adminEmail,
                'admin_password' => $adminPassword,
            ]);
        }

        $tenant->domains()->firstOrCreate(['domain' => $tenantId]);
        $tenant->domains()->firstOrCreate(['domain' => "{$tenantId}.{$baseDomain}"]);

        try {
            $this->safeLog('info', "Running tenant migrate:fresh for '{$tenantId}'...");
            $migrateFreshExitCode = Artisan::call('tenants:migrate-fresh', [
                '--tenants' => [$tenantId],
                '--force' => true,
                '--no-interaction' => true,
            ]);
            $migrateFreshOutput = Artisan::output();

            if ($migrateFreshExitCode !== 0) {
                throw new RuntimeException("Tenant migrate:fresh failed for '{$tenantId}'. Output: {$migrateFreshOutput}");
            }

            $this->safeLog('info', "Running tenant seed for '{$tenantId}'...");
            $seedExitCode = Artisan::call('tenants:seed', [
                '--tenants' => [$tenantId],
                '--class' => 'TenantDatabaseSeeder',
                '--force' => true,
                '--no-interaction' => true,
            ]);
            $seedOutput = Artisan::output();

            if ($seedExitCode !== 0) {
                throw new RuntimeException("Tenant seed failed for '{$tenantId}'. Output: {$seedOutput}");
            }

            $this->assertTenantInitialized($tenant, $adminEmail);
            $tenant->update(['admin_password' => null]);

            $this->safeLog('info', "Tenant database initialized for '{$tenantId}'", [
                'migrate_fresh_output' => $migrateFreshOutput,
                'seed_output' => $seedOutput,
            ]);
        } catch (Throwable $e) {
            $this->safeLog('error', "Tenant bootstrap failed for '{$tenantId}': ".$e->getMessage());
            $this->safeLog('error', $e->getTraceAsString());

            if (! $isExistingTenant) {
                $tenant->delete();
            }

            return response()->json([
                'message' => 'Tenant provisioning failed during migrate:fresh + seed.',
                'error' => $e->getMessage(),
            ], 500);
        }

        // Generate SSL certificate synchronously using Name.com DNS-01 script
        $domain = "{$tenantId}.{$baseDomain}";
        $sslScriptPath = base_path((string) env('SSL_DNS_SCRIPT_PATH', 'scripts/namecom_certbot_dns01.py'));

        try {
            $this->safeLog('info', "Generating SSL certificate for {$domain} using DNS-01 script...");

            if (! file_exists($sslScriptPath)) {
                throw new RuntimeException("SSL script not found at: {$sslScriptPath}");
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
        } catch (Throwable $e) {
            $this->safeLog('warning', "SSL generation warning for {$domain}: ".$e->getMessage());
            // Don't fail tenant creation if SSL fails
        }

        return response()->json([
            'message' => $isExistingTenant
                ? 'Tenant reprovisioned successfully'
                : 'Tenant created successfully',
            'tenant' => $tenant->load('domains'),
            'access' => [
                'url' => "{$scheme}://{$tenantId}.{$baseDomain}{$port}",
                'admin_email' => $adminEmail,
                'admin_password' => $adminPassword,
            ],
        ], $isExistingTenant ? 200 : 201);
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
        } catch (Throwable $e) {
        }
    }

    private function isCentralSchemaReady(): bool
    {
        return Schema::hasTable('tenants') && Schema::hasTable('domains');
    }

    private function assertTenantInitialized(Tenant $tenant, string $adminEmail): void
    {
        tenancy()->initialize($tenant);

        try {
            if (! Schema::hasTable('users')) {
                throw new RuntimeException('Tenant users table was not created.');
            }

            if (! User::query()->where('email', $adminEmail)->exists()) {
                throw new RuntimeException('Tenant admin user was not seeded correctly.');
            }
        } finally {
            tenancy()->end();
        }
    }
}
