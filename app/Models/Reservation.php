<?php

namespace App\Models;

use App\Services\ReservationAvailabilityService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $reservation_id
 * @property int $user_id
 * @property int $vehicle_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property string|null $pickup_location
 * @property string|null $dropoff_location
 * @property string $status
 * @property string|null $stripe_session_id
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $total_cost
 */
class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (Reservation $reservation): void {
            $availability = app(ReservationAvailabilityService::class);
            $availability->syncVehicleAvailability((int) $reservation->vehicle_id);

            if ($reservation->wasChanged('vehicle_id')) {
                $originalVehicleId = (int) $reservation->getOriginal('vehicle_id');
                if ($originalVehicleId > 0 && $originalVehicleId !== (int) $reservation->vehicle_id) {
                    $availability->syncVehicleAvailability($originalVehicleId);
                }
            }
        });

        static::deleted(function (Reservation $reservation): void {
            app(ReservationAvailabilityService::class)->syncVehicleAvailability((int) $reservation->vehicle_id);
        });
    }

    protected $table = 'reservations';

    protected $primaryKey = 'reservation_id';

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'start_date',
        'end_date',
        'pickup_location',
        'dropoff_location',
        'status',
        'stripe_session_id',
        'paid_at',
        'total_cost',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'paid_at' => 'datetime',
        'total_cost' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'reservation_id', 'reservation_id');
    }
}
