<?php

namespace Tests\Unit;

use App\Services\PriceCalculatorService;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PriceCalculatorServiceTest extends TestCase
{
    public function test_calculates_exact_hourly_price(): void
    {
        $service = new PriceCalculatorService(5.0, 'exact');

        $start = Carbon::parse('2026-05-05 10:00:00');
        $end = Carbon::parse('2026-05-05 13:00:00');

        $price = $service->calculateReservationPrice($start, $end);

        $this->assertSame(15.0, $price);
    }

    public function test_calculates_exact_fractional_hours(): void
    {
        $service = new PriceCalculatorService(5.0, 'exact');

        $start = Carbon::parse('2026-05-05 10:00:00');
        $end = Carbon::parse('2026-05-05 15:30:00');

        $price = $service->calculateReservationPrice($start, $end);

        $this->assertSame(27.5, $price);
    }

    public function test_rounds_up_when_configured(): void
    {
        $service = new PriceCalculatorService(5.0, 'round_up');

        $start = Carbon::parse('2026-05-05 10:00:00');
        $end = Carbon::parse('2026-05-05 15:30:00');

        $price = $service->calculateReservationPrice($start, $end);

        $this->assertSame(30.0, $price);
    }

    public function test_rejects_zero_or_negative_duration(): void
    {
        $service = new PriceCalculatorService(5.0, 'exact');

        $start = Carbon::parse('2026-05-05 10:00:00');
        $end = Carbon::parse('2026-05-05 10:00:00');

        $this->expectException(InvalidArgumentException::class);
        $service->calculateReservationPrice($start, $end);
    }
}
