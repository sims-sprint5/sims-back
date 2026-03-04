<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Vehicle;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 days', '+30 days');
        $endDate = fake()->dateTimeBetween($startDate, '+60 days');

        return [
            'user_id' => User::factory(),
            'vehicle_id' => Vehicle::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pickup_location' => fake()->randomElement(['Barcelona Airport', 'BCN Downtown', 'Port Vell', 'Gràcia']),
            'dropoff_location' => fake()->randomElement(['Barcelona Airport', 'BCN Downtown', 'Port Vell', 'Gràcia']),
            'status' => fake()->randomElement(['pending', 'active', 'completed', 'cancelled']),
            'total_cost' => fake()->randomFloat(2, 50, 500),
        ];
    }
}
