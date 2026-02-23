<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usuarios
        DB::table('users')->insert([
            [
                'name' => 'Admin User',
                'email' => 'admin@blink.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '+34600000001',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Juan Pérez',
                'email' => 'juan@example.com',
                'password' => Hash::make('password'),
                'role' => 'user',
                'phone' => '+34600000002',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'María García',
                'email' => 'maria@example.com',
                'password' => Hash::make('password'),
                'role' => 'user',
                'phone' => '+34600000003',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Vehículos
        DB::table('vehicles')->insert([
            [
                'license_plate' => 'ABC1234',
                'brand' => 'Tesla',
                'model' => 'Model 3',
                'year' => 2023,
                'color' => 'Blanco',
                'status' => 'available',
                'current_latitude' => 41.3851,
                'current_longitude' => 2.1734,
                'last_location_update' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'license_plate' => 'XYZ9876',
                'brand' => 'Renault',
                'model' => 'Zoe',
                'year' => 2022,
                'color' => 'Azul',
                'status' => 'reserved',
                'current_latitude' => 41.3879,
                'current_longitude' => 2.1699,
                'last_location_update' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'license_plate' => 'DEF5678',
                'brand' => 'Nissan',
                'model' => 'Leaf',
                'year' => 2024,
                'color' => 'Negro',
                'status' => 'maintenance',
                'current_latitude' => 41.3900,
                'current_longitude' => 2.1600,
                'last_location_update' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Reservas
        DB::table('reservations')->insert([
            [
                'user_id' => 2,
                'vehicle_id' => 2,
                'start_date' => now(),
                'end_date' => now()->addDays(3),
                'pickup_location' => 'Barcelona - Plaça Catalunya',
                'dropoff_location' => 'Barcelona - Sagrada Familia',
                'status' => 'active',
                'total_cost' => 150.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 3,
                'vehicle_id' => 1,
                'start_date' => now()->addDays(5),
                'end_date' => now()->addDays(7),
                'pickup_location' => 'Barcelona - Aeropuerto',
                'dropoff_location' => 'Barcelona - Puerto',
                'status' => 'pending',
                'total_cost' => 200.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Tickets
        DB::table('tickets')->insert([
            [
                'user_id' => 2,
                'vehicle_id' => 2,
                'reservation_id' => 1,
                'type' => 'technical',
                'subject' => 'Batería baja',
                'description' => 'El vehículo muestra 15% de batería, necesito cargar urgentemente',
                'priority' => 'high',
                'status' => 'open',
                'assigned_to' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 3,
                'vehicle_id' => 3,
                'reservation_id' => null,
                'type' => 'complaint',
                'subject' => 'Vehículo sucio',
                'description' => 'El interior del vehículo necesita limpieza',
                'priority' => 'medium',
                'status' => 'in_progress',
                'assigned_to' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Geofences
        DB::table('geofences')->insert([
            [
                'name' => 'Zona Centro Barcelona',
                'description' => 'Área permitida para circulación en el centro',
                'type' => 'allowed',
                'center_latitude' => 41.3851,
                'center_longitude' => 2.1734,
                'radius' => 5000,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Parking Sagrada Familia',
                'description' => 'Zona de estacionamiento autorizada',
                'type' => 'parking',
                'center_latitude' => 41.4036,
                'center_longitude' => 2.1744,
                'radius' => 500,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Zona Restringida Aeropuerto',
                'description' => 'Prohibido circular sin autorización',
                'type' => 'restricted',
                'center_latitude' => 41.2971,
                'center_longitude' => 2.0785,
                'radius' => 2000,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Vehicle Geofence Logs
        DB::table('vehicle_geofence_logs')->insert([
            [
                'vehicle_id' => 1,
                'geofence_id' => 1,
                'event_type' => 'entry',
                'event_timestamp' => now()->subHours(2),
                'latitude' => 41.3851,
                'longitude' => 2.1734,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'vehicle_id' => 2,
                'geofence_id' => 2,
                'event_type' => 'entry',
                'event_timestamp' => now()->subHour(),
                'latitude' => 41.4036,
                'longitude' => 2.1744,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'vehicle_id' => 2,
                'geofence_id' => 3,
                'event_type' => 'violation',
                'event_timestamp' => now()->subMinutes(30),
                'latitude' => 41.2971,
                'longitude' => 2.0785,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
