<?php

namespace App\Modules\Central\Seeders;

use App\Modules\Central\Models\GlobalAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GlobalAdminSeeder extends Seeder
{
    public function run(): void
    {
        GlobalAdmin::firstOrCreate([
            'email' => 'admin@sims.local',
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
        ]);
    }
}
