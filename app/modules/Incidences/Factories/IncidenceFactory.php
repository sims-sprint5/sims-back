<?php

namespace App\Modules\Incidences\Factories;

use App\Modules\Incidences\Models\Incidence;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class IncidenceFactory extends Factory
{
    protected $model = Incidence::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['reported', 'investigating', 'resolved', 'closed']);
        $isResolved = in_array($status, ['resolved', 'closed']);

        // Get a random user ID from the current tenant's users table
        $userId = \DB::table('users')->inRandomOrder()->value('id') ?? 1;

        return [
            'incident_number' => 'INC-'.strtoupper(Str::random(10)),
            'reported_by' => $userId,
            'type' => $this->faker->randomElement(['Technical', 'Maintenance', 'UserComplaint', 'Accident', 'other']),
            'description' => $this->faker->sentence(10),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => $status,
            'resolution_notes' => $isResolved ? $this->faker->sentence(8) : null,
            'resolved_at' => $isResolved ? now() : null,
        ];
    }
}
