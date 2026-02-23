<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\Geofence;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear usuarios
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@blink.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'phone' => '123456789',
            'status' => 'active',
        ]);

        $user1 = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'phone' => '987654321',
            'status' => 'active',
        ]);

        $user2 = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'phone' => '555555555',
            'status' => 'active',
        ]);

        // Crear vehículos
        $vehicle1 = Vehicle::create([
            'license_plate' => 'ABC-1234',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'color' => 'Blue',
            'status' => 'available',
            'current_latitude' => 41.3851,
            'current_longitude' => 2.1734,
        ]);

        $vehicle2 = Vehicle::create([
            'license_plate' => 'XYZ-5678',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2023,
            'color' => 'Red',
            'status' => 'available',
            'current_latitude' => 41.3879,
            'current_longitude' => 2.1699,
        ]);

        $vehicle3 = Vehicle::create([
            'license_plate' => 'DEF-9012',
            'brand' => 'Ford',
            'model' => 'Focus',
            'year' => 2021,
            'color' => 'Black',
            'status' => 'maintenance',
            'current_latitude' => 41.3900,
            'current_longitude' => 2.1800,
        ]);

        // Crear reservas
        $reservation1 = Reservation::create([
            'user_id' => $user1->user_id,
            'vehicle_id' => $vehicle1->vehicle_id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(3),
            'pickup_location' => 'Barcelona Airport',
            'dropoff_location' => 'Barcelona City Center',
            'status' => 'pending',
            'total_cost' => 150.00,
        ]);

        $reservation2 = Reservation::create([
            'user_id' => $user2->user_id,
            'vehicle_id' => $vehicle2->vehicle_id,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(7),
            'pickup_location' => 'Barcelona City Center',
            'dropoff_location' => 'Barcelona Airport',
            'status' => 'active',
            'total_cost' => 200.00,
        ]);

        // Crear tickets
        Ticket::create([
            'user_id' => $user1->user_id,
            'vehicle_id' => $vehicle1->vehicle_id,
            'reservation_id' => $reservation1->reservation_id,
            'type' => 'technical',
            'subject' => 'Car has a flat tire',
            'description' => 'The vehicle has a flat tire and needs assistance.',
            'priority' => 'high',
            'status' => 'open',
            'assigned_to' => $admin->user_id,
        ]);

        Ticket::create([
            'user_id' => $user2->user_id,
            'vehicle_id' => null,
            'reservation_id' => null,
            'type' => 'billing',
            'subject' => 'Billing inquiry',
            'description' => 'I have a question about my last invoice.',
            'priority' => 'low',
            'status' => 'in_progress',
            'assigned_to' => $admin->user_id,
        ]);

        // Crear geofences
        Geofence::create([
            'name' => 'Downtown Barcelona',
            'description' => 'Main downtown area',
            'type' => 'allowed',
            'center_latitude' => 41.3851,
            'center_longitude' => 2.1734,
            'radius' => 5000,
            'status' => 'active',
        ]);

        Geofence::create([
            'name' => 'Airport Zone',
            'description' => 'Barcelona Airport parking area',
            'type' => 'parking',
            'center_latitude' => 41.2974,
            'center_longitude' => 2.0833,
            'radius' => 2000,
            'status' => 'active',
        ]);

        Geofence::create([
            'name' => 'Restricted Area',
            'description' => 'No vehicles allowed',
            'type' => 'restricted',
            'center_latitude' => 41.4000,
            'center_longitude' => 2.2000,
            'radius' => 1000,
            'status' => 'active',
        ]);
    }
}
