<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GeofenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'               => fake()->words(2, true),
            'description'        => fake()->sentence(),
            'type'               => fake()->randomElement(['allowed', 'restricted', 'parking', 'service_area']),
            'center_latitude'    => fake()->latitude(41.35, 41.40),
            'center_longitude'   => fake()->longitude(2.10, 2.20),
            'radius'             => fake()->randomElement([100, 250, 500, 1000, 2000]),
            'polygon_coordinates' => null,
            'status'             => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
