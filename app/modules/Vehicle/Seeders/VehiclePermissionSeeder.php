<?php

namespace App\Modules\Vehicle\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class VehiclePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions for vehicles with explicit guard_name
        $view = Permission::firstOrCreate(
            ['name' => 'vehicles.view.all', 'guard_name' => 'tenant']
        );

        $manage = Permission::firstOrCreate(
            ['name' => 'vehicles.manage.all', 'guard_name' => 'tenant']
        );

        $destroy = Permission::firstOrCreate(
            ['name' => 'vehicles.destroy.all', 'guard_name' => 'tenant']
        );

        // Assign permissions to roles
        /** @var Role|null $adminRole */
        $adminRole = Role::where('name', 'admin_tenant')->where('guard_name', 'tenant')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo([$view, $manage, $destroy]);
        }

        /** @var Role|null $workerRole */
        $workerRole = Role::where('name', 'worker')->where('guard_name', 'tenant')->first();
        if ($workerRole) {
            $workerRole->givePermissionTo([$view, $manage]);
        }

        /** @var Role|null $clientRole */
        $clientRole = Role::where('name', 'client')->where('guard_name', 'tenant')->first();
        if ($clientRole) {
            $clientRole->givePermissionTo([$view]);
        }
    }
}
