<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
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
        $this->safeLog('info', '=== [TENANT LIST] Request started ===');

        if (! $this->isCentralSchemaReady()) {
            $this->safeLog('error', '[TENANT LIST] Central schema not ready');

            return response()->json([
                'message' => 'Central database is not initialized. Run central migrations first.',
            ], 503);
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->safeLog('error', '[TENANT LIST] DB connection failed: '.$e->getMessage());

            return response()->json([
                'message' => 'Central database connection failed. Please verify DB host/credentials.',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 503);
        }

        try {
            $tenants = Tenant::with('domains')->get();
            $this->safeLog('info', '[TENANT LIST] ✓ Success');

            return response()->json($tenants);
        } catch (QueryException $e) {
            $this->safeLog('error', '[TENANT LIST] DB error: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to fetch tenants from central database.',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 503);
        } catch (Throwable $e) {
            $this->safeLog('error', '[TENANT LIST] Unexpected error: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to fetch tenants.',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $this->safeLog('info', '=== [TENANT CREATE] Request started ===');

        if (! $this->isCentralSchemaReady()) {
            $this->safeLog('error', '[TENANT CREATE] Central schema not ready');

            return response()->json([
                'message' => 'Central database is not initialized. Run central migrations first.',
            ], 503);
        }

        try {
            DB::connection()->getPdo();
            $this->safeLog('info', '[TENANT CREATE] DB connection OK');
        } catch (Throwable $e) {
            $this->safeLog('error', '[TENANT CREATE] DB connection failed: '.$e->getMessage());

            return response()->json([
                'message' => 'Central database connection failed. Please verify DB host/credentials.',
                'error' => $e->getMessage(),
            ], 503);
        }

        try {
            $request->validate([
                'id' => 'required|string|alpha_dash|max:50',
                'name' => 'required|string|max:255',
                'admin_name' => 'nullable|string|max:255',
                'admin_email' => 'nullable|email|max:255',
                'admin_password' => 'nullable|string|min:8',
            ]);
            $this->safeLog('info', '[TENANT CREATE] Validation passed');
        } catch (Throwable $e) {
            $this->safeLog('error', '[TENANT CREATE] Validation error: '.$e->getMessage());
            throw $e;
        }

        $tenantId = strtolower((string) $request->id);
        $baseDomain = env('TENANT_BASE_DOMAIN', 'localhost');
        $defaultTenantAdminPassword = (string) env('TENANT_DEFAULT_ADMIN_PASSWORD', 'password123');
        $adminPassword = $request->filled('admin_password')
            ? (string) $request->admin_password
            : $defaultTenantAdminPassword;

        $parsedUrl = parse_url((string) config('app.url', ''));
        $scheme = is_array($parsedUrl) ? ($parsedUrl['scheme'] ?? 'http') : 'http';
        $port = is_array($parsedUrl) && isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : '';

        $adminEmail = $request->admin_email ?? "admin@{$tenantId}.{$baseDomain}";
        $tenant = null;
        $isExistingTenant = false;

        // Step 1: Create or update tenant record and domains
        // This is the core operation - if this fails, we exit immediately without rollback
        try {
            $this->safeLog('info', "[TENANT CREATE] Step 1: Looking up tenant '{$tenantId}'");
            $tenant = Tenant::query()->find($tenantId);
            $isExistingTenant = (bool) $tenant;

            if (! $tenant) {
                $this->safeLog('info', "[TENANT CREATE] Creating NEW tenant '{$tenantId}'");
                $tenant = Tenant::create([
                    'id' => $tenantId,
                    'name' => $request->name,
                    'admin_name' => $request->admin_name ?? 'Admin '.ucfirst($tenantId),
                    'admin_email' => $adminEmail,
                    'admin_password' => $adminPassword,
                ]);
                $this->safeLog('info', "[TENANT CREATE] Tenant created: {$tenant->id}");
            } else {
                $this->safeLog('info', "[TENANT CREATE] Updating EXISTING tenant '{$tenantId}'");
                $tenant->update([
                    'name' => $request->name,
                    'admin_name' => $request->admin_name ?? ($tenant->admin_name ?? 'Admin '.ucfirst($tenantId)),
                    'admin_email' => $adminEmail,
                    'admin_password' => $adminPassword,
                ]);
                $this->safeLog('info', "[TENANT CREATE] Tenant updated: {$tenant->id}");
            }

            $this->safeLog('info', '[TENANT CREATE] Creating domains');
            $tenant->domains()->firstOrCreate(['domain' => $tenantId]);
            $tenant->domains()->firstOrCreate(['domain' => "{$tenantId}.{$baseDomain}"]);

            $this->safeLog('info', "[TENANT CREATE] ✓ Tenant '{$tenantId}' in database");
        } catch (QueryException $e) {
            $this->safeLog('error', '[TENANT CREATE] ❌ DB error: '.$e->getMessage());
            $this->safeLog('error', '[TENANT CREATE] SQL State: '.($e->errorInfo[0] ?? 'unknown'));

            return response()->json([
                'message' => 'Failed to create/update tenant in database.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (Throwable $e) {
            $this->safeLog('error', '[TENANT CREATE] ❌ Error: '.$e->getMessage());
            $this->safeLog('error', '[TENANT CREATE] Type: '.class_basename($e));

            return response()->json([
                'message' => 'Failed to create tenant.',
                'error' => $e->getMessage(),
            ], 500);
        }

        // Step 2: Run migrate:fresh + seed
        // These are secondary operations - if they fail, the tenant already exists
        try {
            $this->safeLog('info', "[TENANT CREATE] Step 2: migrate:fresh for '{$tenantId}'");
            $migrateFreshExitCode = Artisan::call('tenants:migrate-fresh', [
                '--tenants' => [$tenantId],
                '--no-interaction' => true,
            ]);
            $migrateFreshOutput = Artisan::output();

            if ($migrateFreshExitCode !== 0) {
                $this->safeLog('error', "[TENANT CREATE] migrate:fresh exit code: {$migrateFreshExitCode}");
                $this->safeLog('error', "[TENANT CREATE] migrate:fresh output: {$migrateFreshOutput}");
                throw new RuntimeException("Migration failed (exit code {$migrateFreshExitCode}): {$migrateFreshOutput}");
            }
            $this->safeLog('info', '[TENANT CREATE] ✓ migrate:fresh OK');

            $this->safeLog('info', "[TENANT CREATE] Step 3: seed for '{$tenantId}'");
            $seedExitCode = Artisan::call('tenants:seed', [
                '--tenants' => [$tenantId],
                '--class' => 'TenantDatabaseSeeder',
                '--no-interaction' => true,
            ]);
            $seedOutput = Artisan::output();

            if ($seedExitCode !== 0) {
                $this->safeLog('error', "[TENANT CREATE] seed exit code: {$seedExitCode}");
                $this->safeLog('error', "[TENANT CREATE] seed output: {$seedOutput}");
                throw new RuntimeException("Seeding failed (exit code {$seedExitCode}): {$seedOutput}");
            }
            $this->safeLog('info', '[TENANT CREATE] ✓ seed OK');

            $this->safeLog('info', '[TENANT CREATE] Step 4: asserting initialization');
            $this->assertTenantInitialized($tenant, $adminEmail);
            $this->safeLog('info', '[TENANT CREATE] ✓ initialization OK');

            $this->safeLog('info', '[TENANT CREATE] Step 5: clearing admin password');
            $tenant->update(['admin_password' => null]);
            $this->safeLog('info', '[TENANT CREATE] ✓ password cleared');

            $this->safeLog('info', '[TENANT CREATE] === SUCCESS ===');
        } catch (Throwable $e) {
            $this->safeLog('error', '[TENANT CREATE] ❌ migrate/seed failed: '.$e->getMessage());
            $this->safeLog('error', '[TENANT CREATE] Type: '.class_basename($e));
            foreach (explode("\n", $e->getTraceAsString()) as $line) {
                if (trim($line)) {
                    $this->safeLog('error', '[TENANT CREATE] '.$line);
                }
            }

            // Tenant already exists in DB - just log the error but don't delete it
            return response()->json([
                'message' => 'Tenant created but initialization failed.',
                'error' => $e->getMessage(),
                'tenant' => $tenant->load('domains'),
                'note' => 'Tenant exists but may lack data. Contact admin to retry seeding.',
            ], 500);
        }

        // Generate SSL certificate synchronously using Name.com DNS-01 script (skip in testing)
        if (! app()->environment('testing')) {
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
