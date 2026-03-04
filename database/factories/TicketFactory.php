<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Reservation;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'vehicle_id'     => Vehicle::factory(),
            'reservation_id' => null,
            'type'           => fake()->randomElement(['technical', 'billing', 'complaint', 'inquiry']),
            'subject'        => fake()->sentence(3),
            'description'    => fake()->paragraph(),
            'priority'       => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status'         => fake()->randomElement(['open', 'in_progress', 'resolved', 'closed']),
            'assigned_to'    => null,
        ];
    }
}
