<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+2 days', '+30 days');
        $endDate = fake()->dateTimeBetween($startDate, '+60 days');

        return [
            'user_id' => User::factory(),
            'vehicle_id' => Vehicle::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pickup_location' => fake()->randomElement(['Main Station', 'City Center', 'Airport Terminal', 'North Hub']),
            'dropoff_location' => fake()->randomElement(['Main Station', 'City Center', 'Airport Terminal', 'North Hub']),
            'status' => 'Completat',
            'total_cost' => fake()->randomFloat(2, 50, 500),
        ];
    }
}
