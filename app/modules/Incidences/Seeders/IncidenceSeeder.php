<?php

namespace App\Modules\Incidences\Seeders;

use App\Modules\Incidences\Models\Incidence;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IncidenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions for incidences with explicit guard_name
        $view = Permission::firstOrCreate(
            ['name' => 'incidences.view.all'],
            ['guard_name' => 'tenant']
        );

        $manage = Permission::firstOrCreate(
            ['name' => 'incidences.manage.all'],
            ['guard_name' => 'tenant']
        );

        $destroy = Permission::firstOrCreate(
            ['name' => 'incidences.destroy.all'],
            ['guard_name' => 'tenant']
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

        // Create test incidences
        Incidence::factory()->count(10)->create();
    }
}
