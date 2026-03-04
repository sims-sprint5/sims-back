<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        $brands = ['Toyota', 'Honda', 'Ford', 'BMW', 'Mercedes', 'Audi'];
        $models = ['Corolla', 'Civic', 'Focus', 'Fiesta', 'Yaris', 'Golf'];
        $colors = ['Blue', 'Red', 'Black', 'White', 'Silver', 'Green'];

        return [
            'license_plate' => fake()->bothify('??#-####'),
            'brand' => fake()->randomElement($brands),
            'model' => fake()->randomElement($models),
            'year' => fake()->numberBetween(2015, 2024),
            'color' => fake()->randomElement($colors),
            'status' => fake()->randomElement(['available', 'reserved', 'maintenance', 'inactive']),
            'current_latitude' => fake()->latitude(41.35, 41.40),
            'current_longitude' => fake()->longitude(2.10, 2.20),
            'last_location_update' => now(),
        ];
    }

    public function available(): static
    {
        return $this->state(['status' => 'available']);
    }
}
