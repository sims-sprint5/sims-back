<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password123'),
            'role' => fake()->randomElement(['user', 'admin', 'technical']),
            'phone' => fake()->phoneNumber(),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function user(): static
    {
        return $this->state(['role' => 'user']);
    }
}
