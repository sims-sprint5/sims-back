<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('superadmin.email', ''));
        $name = trim((string) config('superadmin.name', ''));
        $password = (string) config('superadmin.password', '');

        if (app()->environment('production') && ($email === '' || $name === '' || $password === '')) {
            throw new \RuntimeException('Missing SUPERADMIN_NAME, SUPERADMIN_EMAIL or SUPERADMIN_PASSWORD in production environment.');
        }

        if ($email === '') {
            $email = 'superadmin@example.com';
        }

        if ($name === '') {
            $name = 'Super Admin';
        }

        if ($password === '') {
            $password = 'changeme';
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
