<?php

namespace App\Modules\Vehicle\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $license_plate
 * @property string|null $vin
 * @property string $brand
 * @property string $model
 * @property string $type
 * @property string $status
 * @property int|null $year
 * @property string|null $color
 * @property int|null $battery_level
 * @property int|null $range_km
 * @property float|null $price_per_minute
 * @property float|null $price_per_hour
 * @property mixed|null $location
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The guard used by the Vehicle model for Spatie Permission checks.
     *
     * @var string
     */
    protected $guard_name = 'tenant';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'license_plate',
        'vin',
        'brand',
        'model',
        'type',
        'status',
        'year',
        'color',
        'battery_level',
        'range_km',
        'price_per_minute',
        'price_per_hour',
        'location',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'battery_level' => 'integer',
            'range_km' => 'integer',
            'price_per_minute' => 'decimal:2',
            'price_per_hour' => 'decimal:2',
            'metadata' => 'json',
            'deleted_at' => 'datetime',
            'location' => 'null', // PostGIS geography(POINT, 4326) - handled by database
        ];
    }
}
