<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $vehicle_id
 * @property string $license_plate
 * @property string $brand
 * @property string $model
 * @property int|null $year
 * @property string|null $color
 * @property string $status
 * @property string|null $current_latitude
 * @property string|null $current_longitude
 * @property \Illuminate\Support\Carbon|null $last_location_update
 */
class Vehicle extends Model
{
    use HasFactory;

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
        'last_location_update',
    ];

    protected $casts = [
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'last_location_update' => 'datetime',
    ];

    // Relationships
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
