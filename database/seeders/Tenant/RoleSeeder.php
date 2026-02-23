<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guardName = config('auth.defaults.guard', 'web');

        Role::create(['name' => 'tenant_admin', 'guard_name' => $guardName]);
        Role::create(['name' => 'manager', 'guard_name' => $guardName]);
        Role::create(['name' => 'client', 'guard_name' => $guardName]);
    }
}
