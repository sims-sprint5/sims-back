<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) (env('SUPERADMIN_EMAIL') ?? ''));
        $name = trim((string) (env('SUPERADMIN_NAME') ?? ''));
        $password = (string) (env('SUPERADMIN_PASSWORD') ?? 'changeme');

        if ($email === '') {
            $email = 'superadmin@example.com';
        }

        if ($name === '') {
            $name = 'Super Admin';
        }

        SuperAdmin::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );
    }
}
