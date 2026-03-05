<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        SuperAdmin::firstOrCreate(
            ['email' => env('SUPERADMIN_EMAIL')],
            [
                'name' => env('SUPERADMIN_NAME'),
                'password' => Hash::make(env('SUPERADMIN_PASSWORD', 'changeme')),
            ]
        );
    }
}
