<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Modules\Auth\Seeders\RolePermissionSeeder;
use App\Modules\Incidences\Seeders\IncidenceSeeder;
use App\Modules\User\Models\User;
use App\Modules\User\Seeders\UserSeeder;
use App\Modules\Vehicle\Seeders\VehiclePermissionSeeder;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Database\Models\Domain;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Tenant 1
        $tenant1 = Tenant::create([
            'id' => '1',
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'plan_id' => 1,
            'status' => 'active',
        ]);

        Domain::create([
            'tenant_id' => '1',
            'domain' => 'acme.local',
            'is_primary' => true,
        ]);

        // Tenant 2
        $tenant2 = Tenant::create([
            'id' => '2',
            'name' => 'Pollos Hermanos',
            'slug' => 'pollos-hermanos',
            'plan_id' => 1,
            'status' => 'active',
        ]);

        Domain::create([
            'tenant_id' => '2',
            'domain' => 'pollos.local',
            'is_primary' => true,
        ]);

        // Run tenant migrations for both tenants
        $this->runTenantMigrations('1');
        $this->runTenantMigrations('2');
    }

    private function runTenantMigrations(string $tenantId): void
    {
        // Initialize tenancy for this tenant
        $tenant = Tenant::find($tenantId);
        tenancy()->initialize($tenant);

        try {
            // Create schema explicitly
            $schemaName = 'tenant_'.$tenant->id;
            \DB::statement("CREATE SCHEMA IF NOT EXISTS \"$schemaName\"");
            \DB::connection('pgsql')->statement("SET search_path TO \"$schemaName\", public");

            // Run migrations directly in the tenant schema
            \Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
                '--database' => 'pgsql',
            ]);

            $this->call(RolePermissionSeeder::class);
            $this->call(UserSeeder::class);
            $this->call(VehiclePermissionSeeder::class);

            $this->createTenantUsers();

            $this->call(IncidenceSeeder::class);

            $this->command->info("✓ Tenant {$tenantId} migrated and seeded successfully");
        } finally {
            tenancy()->end();
        }
    }

    private function createTenantUsers(): void
    {
        $users = [
            [
                'name' => 'Admin Tenant',
                'email' => 'admin@'.tenant('id').'.com',
                'password' => bcrypt('password'),
                'role' => 'admin_tenant',
            ],
            [
                'name' => 'Worker Tenant',
                'email' => 'worker@'.tenant('id').'.com',
                'password' => bcrypt('password'),
                'role' => 'worker',
            ],
            [
                'name' => 'Client Tenant',
                'email' => 'client@'.tenant('id').'.com',
                'password' => bcrypt('password'),
                'role' => 'client',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            if ($user && Role::where('name', $role)->where('guard_name', 'tenant')->exists()) {
                $user->assignRole($role);
            }
        }
    }
}
