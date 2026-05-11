<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant('id');

        // Custom fields are stored as top-level attributes on the tenant model
        // and accessed via the tenant() helper.
        $adminName = tenant('admin_name') ?? 'Admin '.ucfirst($tenantId);
        $adminEmail = tenant('admin_email') ?? "admin@{$tenantId}.local";
        $defaultTenantAdminPassword = (string) env('TENANT_DEFAULT_ADMIN_PASSWORD', 'password123');
        $adminPassword = tenant('admin_password');
        if (! is_string($adminPassword) || trim($adminPassword) === '') {
            $adminPassword = $defaultTenantAdminPassword;
        }

        $admin = User::create([
            'name' => $adminName,
            'email' => $adminEmail,
            'password' => Hash::make($adminPassword),
            'role' => 'admin',
            'phone' => null,
            'status' => 'active',
        ]);

        // Factories disabled in production - seeding only admin user
        $users = User::factory()->count(5)->user()->create();
        $allUsers = $users->push($admin);

        // Create initial Spatie roles and assign Super Admin to the first user in this tenant.
        $this->call(RolesAndPermissionsSeeder::class);

        // Vehicle, reservation and geofence seeders removed — data is created manually.
    }
}
