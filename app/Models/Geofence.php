<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Geofence extends Model
{
    protected $primaryKey = 'geofence_id';

    protected $fillable = [
        'name',
        'description',
        'type',
        'center_latitude',
        'center_longitude',
        'radius',
        'polygon_coordinates',
        'status'
    ];

    protected $casts = [
        'center_latitude' => 'decimal:8',
        'center_longitude' => 'decimal:8',
        'polygon_coordinates' => 'array',
    ];

    // Relaciones
    public function vehicleLogs()
    {
        return $this->hasMany(VehicleGeofenceLog::class, 'geofence_id', 'geofence_id');
    }
}
