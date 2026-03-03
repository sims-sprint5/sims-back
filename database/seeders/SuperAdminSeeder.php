<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SuperAdmin::create([
            'name'     => 'Super Admin',
            'email'    => 'superadmin@sims.com',
            'password' => 'superadmin123', // Modificar-ho per utilitzar password del .env
        ]);
    }
}
