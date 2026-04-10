<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Vehicle;

class ReservationAvailabilityService
{
    private array $activeStatuses = ['pending', 'active'];

    public function releaseExpiredReservations(): void
    {
        $expiredReservations = Reservation::query()
            ->whereIn('status', $this->activeStatuses)
            ->where('end_date', '<=', now())
            ->get();

        if ($expiredReservations->isEmpty()) {
            return;
        }

        $vehicleIds = [];

        foreach ($expiredReservations as $reservation) {
            $vehicleIds[] = (int) $reservation->vehicle_id;

            if ($reservation->status !== 'completed') {
                $reservation->update(['status' => 'completed']);
            }
        }

        foreach (array_unique($vehicleIds) as $vehicleId) {
            $this->syncVehicleAvailability((int) $vehicleId);
        }
    }

    public function vehicleHasActiveReservation(int $vehicleId, ?int $exceptReservationId = null): bool
    {
        // Only consider a reservation "active" if it has already started (start_date <= now)
        // and hasn't ended yet (end_date > now)
        return Reservation::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', $this->activeStatuses)
            ->where('start_date', '<=', now())
            ->where('end_date', '>', now())
            ->when($exceptReservationId, function ($query, $exceptReservationId) {
                $query->where('reservation_id', '!=', $exceptReservationId);
            })
            ->exists();
    }

    public function userOwnsActiveReservationForVehicle(int $vehicleId, int $userId): bool
    {
        return Reservation::query()
            ->where('vehicle_id', $vehicleId)
            ->where('user_id', $userId)
            ->whereIn('status', $this->activeStatuses)
            ->where('start_date', '<=', now())
            ->where('end_date', '>', now())
            ->exists();
    }

    /**
     * Get the next upcoming reservation for a vehicle (even if not started yet).
     */
    public function getNextUpcomingReservation(int $vehicleId): ?Reservation
    {
        return Reservation::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', $this->activeStatuses)
            ->where('start_date', '>', now())
            ->orderBy('start_date', 'asc')
            ->first();
    }

    public function syncVehicleAvailability(int $vehicleId): void
    {
        $vehicle = Vehicle::query()->find($vehicleId);

        if (! $vehicle) {
            return;
        }

        if ($this->vehicleHasActiveReservation($vehicleId)) {
            if ($vehicle->status !== 'reserved') {
                $vehicle->update(['status' => 'reserved']);
            }

            return;
        }

        if ($vehicle->status === 'reserved') {
            $vehicle->update(['status' => 'available']);
        }
    }

    /**
     * Check if a vehicle is available for a specific date/time period.
     * 
     * Returns:
     * - ['available' => true] if no conflicts
     * - ['available' => false, 'message' => '...', 'available_at' => '...'] if there's a conflict
     */
    public function checkAvailabilityForPeriod(
        int $vehicleId,
        \DateTime $startDate,
        \DateTime $endDate,
        ?int $exceptReservationId = null
    ): array {
        // Look for any reservations that overlap with the requested period
        $conflictingReservation = Reservation::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', $this->activeStatuses)
            ->when($exceptReservationId, function ($query, $exceptReservationId) {
                $query->where('reservation_id', '!=', $exceptReservationId);
            })
            // Check for overlapping periods:
            // start_date < requested_end_date AND end_date > requested_start_date
            ->where('start_date', '<', $endDate)
            ->where('end_date', '>', $startDate)
            ->orderBy('end_date', 'asc')
            ->first();

        if (! $conflictingReservation) {
            return ['available' => true];
        }

        // Vehicle not available - return when it will be available
        return [
            'available' => false,
            'message' => "Vehicle is not available for the requested period. It will be available from {$conflictingReservation->end_date->format('Y-m-d H:i:s')}.",
            'available_at' => $conflictingReservation->end_date->toIso8601String(),
            'conflicting_reservation' => [
                'start_date' => $conflictingReservation->start_date->toIso8601String(),
                'end_date' => $conflictingReservation->end_date->toIso8601String(),
            ],
        ];
    }
}
