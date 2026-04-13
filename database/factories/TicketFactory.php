<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_id' => Vehicle::factory(),
            'reservation_id' => null,
            'type' => fake()->randomElement(['technical', 'billing', 'complaint', 'inquiry']),
            'subject' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(['alta', 'mitjana', 'baixa']),
            'status' => fake()->randomElement(['obert', 'en_progres', 'finalitzat']),
            'assigned_to' => null,
        ];
    }
}
