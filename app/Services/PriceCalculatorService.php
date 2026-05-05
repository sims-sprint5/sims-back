<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;

class PriceCalculatorService
{
    private float $hourlyRateEur;

    private string $roundingMode;

    public function __construct(?float $hourlyRateEur = null, ?string $roundingMode = null)
    {
        if ($hourlyRateEur === null) {
            $hourlyRateEur = (float) config('services.reservations.hourly_rate_eur', 5.0);
        }

        if ($roundingMode === null) {
            $roundingMode = (string) config('services.reservations.rounding', 'exact');
        }

        if ($hourlyRateEur <= 0) {
            throw new InvalidArgumentException('Hourly rate must be greater than 0.');
        }

        $this->hourlyRateEur = $hourlyRateEur;
        $this->roundingMode = $roundingMode;
    }

    public function calculateReservationPrice(DateTimeInterface $startDate, DateTimeInterface $endDate): float
    {
        $start = $startDate instanceof Carbon ? $startDate : Carbon::instance($startDate);
        $end = $endDate instanceof Carbon ? $endDate : Carbon::instance($endDate);

        $minutes = $start->diffInMinutes($end, false);

        if ($minutes <= 0) {
            throw new InvalidArgumentException('Reservation duration must be greater than 0.');
        }

        $hours = $minutes / 60;
        $billableHours = $this->applyRounding($hours);
        $price = $billableHours * $this->hourlyRateEur;
        $price = round($price, 2);

        if ($price <= 0) {
            throw new InvalidArgumentException('Reservation price must be greater than 0.');
        }

        return $price;
    }

    private function applyRounding(float $hours): float
    {
        switch ($this->roundingMode) {
            case 'ceil':
            case 'round_up':
                return (float) ceil($hours);
            case 'exact':
            default:
                return $hours;
        }
    }
}
