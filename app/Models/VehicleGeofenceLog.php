<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleGeofenceLog extends Model
{
    protected $primaryKey = 'log_id';

    protected $fillable = [
        'vehicle_id',
        'geofence_id',
        'event_type',
        'event_timestamp',
        'latitude',
        'longitude'
    ];

    protected $casts = [
        'event_timestamp' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    // Relaciones
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function geofence()
    {
        return $this->belongsTo(Geofence::class, 'geofence_id', 'geofence_id');
    }
}
