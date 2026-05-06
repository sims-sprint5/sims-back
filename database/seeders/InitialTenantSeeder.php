<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class InitialTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = env('INITIAL_TENANT_ID');
        $tenantDomain = env('INITIAL_TENANT_DOMAIN');

        if (! $tenantId || ! $tenantDomain) {
            return;
        }

        $tenant = Tenant::firstOrCreate(
            ['id' => $tenantId],
            [
                'name' => env('INITIAL_TENANT_NAME', ucfirst($tenantId)),
                'admin_name' => env('INITIAL_TENANT_ADMIN_NAME', 'Admin '.ucfirst($tenantId)),
                'admin_email' => env('INITIAL_TENANT_ADMIN_EMAIL', "admin@{$tenantId}.local"),
                'admin_password' => env('INITIAL_TENANT_ADMIN_PASSWORD', env('TENANT_DEFAULT_ADMIN_PASSWORD', 'password123')),
            ]
        );

        $tenant->domains()->firstOrCreate(['domain' => $tenantDomain]);

        if ($tenant->wasRecentlyCreated) {
            $tenant->run(function () {
                $this->call(RolesAndPermissionsSeeder::class);
            });
        }
    }
}
