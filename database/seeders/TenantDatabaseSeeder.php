<?php

namespace Database\Seeders;

use App\Models\Geofence;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vehicle;
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

        // Factories disabled in production
        $vehicles = Vehicle::factory()->count(8)->create();

        for ($i = 0; $i < 6; $i++) {
            Reservation::factory()->create([
                'user_id' => $allUsers->random()->user_id,
                'vehicle_id' => $vehicles->random()->vehicle_id,
            ]);
        }

        for ($i = 0; $i < 10; $i++) {
            Ticket::factory()->create([
                'user_id' => $allUsers->random()->user_id,
                'vehicle_id' => $vehicles->random()->vehicle_id,
            ]);
        }

        Geofence::factory()->count(5)->create();
    }
}
