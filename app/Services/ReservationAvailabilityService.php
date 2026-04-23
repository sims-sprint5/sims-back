<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Vehicle;

class ReservationAvailabilityService
{
    private array $activeStatuses = ['pending', 'paid', 'active'];

    public function releaseExpiredReservations(): void
    {
        $expiredReservations = Reservation::query()
            ->whereIn('status', $this->activeStatuses)
            ->whereDate('end_date', '<', now()->toDateString())
            ->get();

        if ($expiredReservations->isEmpty()) {
            return;
        }

        $vehicleIds = [];

        foreach ($expiredReservations as $reservation) {
            /** @var Reservation $reservation */
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
        // Day-based reservation semantics: a reservation is active while today is within
        // [start_date, end_date], both boundaries inclusive.
        return Reservation::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', $this->activeStatuses)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
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
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
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
            ->whereDate('start_date', '>', now()->toDateString())
            ->orderBy('start_date', 'asc')
            ->first();
    }

    public function syncVehicleAvailability(int $vehicleId): void
    {
        $vehicle = Vehicle::query()->find($vehicleId);

        if (! $vehicle) {
            return;
        }

        // Check if there's any ACTIVE reservation (started but not ended)
        $hasActiveReservation = $this->vehicleHasActiveReservation($vehicleId);

        if ($hasActiveReservation) {
            // Vehicle is currently in use
            if ((string) $vehicle->status !== 'reserved') {
                $vehicle->update(['status' => 'reserved']);
            }
        } else {
            // No active reservation - vehicle should be available
            if ((string) $vehicle->status !== 'available' && (string) $vehicle->status !== 'inactive' && (string) $vehicle->status !== 'maintenance') {
                $vehicle->update(['status' => 'available']);
            }
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
            // Day-based overlap (inclusive):
            // existing_start <= requested_end AND existing_end >= requested_start
            ->whereDate('start_date', '<=', $endDate->format('Y-m-d'))
            ->whereDate('end_date', '>=', $startDate->format('Y-m-d'))
            ->orderBy('end_date', 'asc')
            ->first();

        if (! $conflictingReservation) {
            return ['available' => true];
        }

        $availableAt = $conflictingReservation->end_date->copy()->addDay()->startOfDay();

        // Vehicle not available - return the next available date.
        return [
            'available' => false,
            'message' => 'Vehicle is not available for the requested period. It will be available from '.$availableAt->format('Y-m-d H:i:s').'.',
            'available_at' => $availableAt->toIso8601String(),
            'conflicting_reservation' => [
                'start_date' => $conflictingReservation->start_date->toIso8601String(),
                'end_date' => $conflictingReservation->end_date->toIso8601String(),
            ],
        ];
    }

    /**
     * Sync all vehicles availability status based on their reservations.
     */
    public function syncAllVehiclesAvailability(): void
    {
        $vehicles = Vehicle::all();
        foreach ($vehicles as $vehicle) {
            $this->syncVehicleAvailability((int) $vehicle->vehicle_id);
        }
    }
}
