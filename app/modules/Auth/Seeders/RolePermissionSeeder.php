<?php

namespace App\Modules\Auth\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {

        Role::firstOrCreate(
            ['name' => 'admin_tenant', 'guard_name' => 'tenant'],
            ['guard_name' => 'tenant']
        );

        Role::firstOrCreate(
            ['name' => 'worker', 'guard_name' => 'tenant'],
            ['guard_name' => 'tenant']
        );

        Role::firstOrCreate(
            ['name' => 'client', 'guard_name' => 'tenant'],
            ['guard_name' => 'tenant']
        );
    }
}
