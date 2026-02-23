<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $table = 'vehicles';
    protected $primaryKey = 'vehicle_id';

    protected $fillable = [
        'license_plate',
        'brand',
        'model',
        'year',
        'color',
        'status',
        'current_latitude',
        'current_longitude',
        'last_location_update'
    ];

    protected $casts = [
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'last_location_update' => 'datetime',
    ];

    // Relaciones
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'vehicle_id', 'vehicle_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'vehicle_id', 'vehicle_id');
    }

    public function geofenceLogs()
    {
        return $this->hasMany(VehicleGeofenceLog::class, 'vehicle_id', 'vehicle_id');
    }
}
