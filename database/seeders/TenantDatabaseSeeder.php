<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\Geofence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant('id');
        $data     = tenant('data') ?? [];

        // Admin personalitzat o per defecte
        $adminName     = $data['admin_name']     ?? 'Admin ' . ucfirst($tenantId);
        $adminEmail    = $data['admin_email']    ?? "admin@{$tenantId}.local";
        $adminPassword = $data['admin_password'] ?? 'password123';

        $admin = User::create([
            'name'     => $adminName,
            'email'    => $adminEmail,
            'password' => Hash::make($adminPassword),
            'role'     => 'admin',
            'phone'    => null,
            'status'   => 'active',
        ]);

        $users    = User::factory()->count(5)->user()->create();
        $allUsers = $users->push($admin);

        $vehicles = Vehicle::factory()->count(8)->create();

        for ($i = 0; $i < 6; $i++) {
            Reservation::factory()->create([
                'user_id'    => $allUsers->random()->user_id,
                'vehicle_id' => $vehicles->random()->vehicle_id,
            ]);
        }

        for ($i = 0; $i < 10; $i++) {
            Ticket::factory()->create([
                'user_id'    => $allUsers->random()->user_id,
                'vehicle_id' => $vehicles->random()->vehicle_id,
            ]);
        }

        Geofence::factory()->count(5)->create();
        
        $currentTenant = \App\Models\Tenant::find(tenant('id'));
        $data = $currentTenant->data ?? [];
        unset($data['admin_password']);
        $currentTenant->update(['data' => $data]);
    }
}
