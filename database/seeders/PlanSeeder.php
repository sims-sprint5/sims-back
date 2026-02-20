<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Create basic plan
        \DB::table('plans')->insert([
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small businesses',
                'price' => 9.99,
                'billing_interval' => 'monthly',
                'max_users' => 5,
                'max_vehicles' => 10,
                'is_active' => true,
                'metadata' => json_encode(['tier' => 'basic']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For growing teams',
                'price' => 29.99,
                'billing_interval' => 'monthly',
                'max_users' => 50,
                'max_vehicles' => 100,
                'is_active' => true,
                'metadata' => json_encode(['tier' => 'professional']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large organizations',
                'price' => 99.99,
                'billing_interval' => 'yearly',
                'max_users' => null,
                'max_vehicles' => null,
                'is_active' => true,
                'metadata' => json_encode(['tier' => 'enterprise']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
