<?php

namespace App\Modules\User\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions for users with explicit guard_name
        $view = Permission::firstOrCreate(
            ['name' => 'users.view.all'],
            ['guard_name' => 'tenant']
        );

        $manage = Permission::firstOrCreate(
            ['name' => 'users.manage.all'],
            ['guard_name' => 'tenant']
        );

        $destroy = Permission::firstOrCreate(
            ['name' => 'users.destroy.all'],
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
            $workerRole->givePermissionTo([$view]);
        }
    }
}
