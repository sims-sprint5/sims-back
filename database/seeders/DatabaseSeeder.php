<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call GlobalAdminSeeder
        $this->call(\App\Modules\Central\Seeders\GlobalAdminSeeder::class);

        // Seed plans first
        $this->call(\Database\Seeders\PlanSeeder::class);

        // Seed tenants (creates schemas and tables)
        $this->call(\Database\Seeders\TenantSeeder::class);
    }
}
