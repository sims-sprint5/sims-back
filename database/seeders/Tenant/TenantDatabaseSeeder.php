<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the tenant database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);
    }
}
