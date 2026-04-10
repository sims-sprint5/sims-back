<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\ReservationAvailabilityService;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    private function isAdmin(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->hasRole('Admin') || strtolower((string) ($user?->role ?? '')) === 'admin');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $availability = app(ReservationAvailabilityService::class);
        $availability->releaseExpiredReservations();

        $perPage = $request->integer('per_page', 15);
        $user = $request->user();
        $query = Vehicle::query()->with(['reservations.user']);

        if (! $this->isAdmin($request) && $user) {
            $userId = (int) $user->user_id;

            // Show vehicles that:
            // 1. Are available (no active/in-progress reservation)
            // 2. OR have a reservation (any) for this user
            // BUT don't show vehicles with an ACTIVE reservation from another user
            $query->where(function ($vehicleQuery) use ($userId) {
                $vehicleQuery
                    ->where('status', 'available')
                    ->orWhereHas('reservations', function ($reservationQuery) use ($userId) {
                        // Include reservations from this user (both started and future)
                        $reservationQuery
                            ->whereIn('status', ['pending', 'active'])
                            ->where('user_id', $userId);
                    });
            })->whereDoesntHave('reservations', function ($reservationQuery) use ($userId) {
                // Exclude vehicles with ACTIVE (started) reservations from other users
                $reservationQuery
                    ->whereIn('status', ['pending', 'active'])
                    ->where('start_date', '<=', now())
                    ->where('end_date', '>', now())
                    ->where('user_id', '!=', $userId);
            });
        }

        $vehicles = $query->paginate($perPage);

        // Enrich each vehicle with upcoming reservation info if available
        $vehicles->getCollection()->transform(function (Vehicle $vehicle) use ($availability) {
            $nextReservation = $availability->getNextUpcomingReservation((int) $vehicle->vehicle_id);
            if ($nextReservation) {
                $vehicle->setAttribute('next_reservation', [
                    'start_date' => $nextReservation->start_date,
                    'end_date' => $nextReservation->end_date,
                    'user_name' => $nextReservation->user?->name,
                ]);
            }
            return $vehicle;
        });

        return response()->json($vehicles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'license_plate' => 'required|string|max:20|unique:vehicles,license_plate',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'color' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:available,reserved,maintenance,inactive',
            'current_latitude' => 'nullable|numeric|between:-90,90',
            'current_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $vehicle = Vehicle::create($validated);

        return response()->json($vehicle, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $availability = app(ReservationAvailabilityService::class);
        $availability->releaseExpiredReservations();

        if (! $this->isAdmin($request) && $request->user()) {
            $userId = (int) $request->user()->user_id;
            $isBlockedForUser = $availability->vehicleHasActiveReservation((int) $id)
                && ! $availability->userOwnsActiveReservationForVehicle((int) $id, $userId);

            if ($isBlockedForUser) {
                return response()->json(['message' => 'Vehicle not available.'], 404);
            }
        }

        $vehicle = Vehicle::with(['reservations.user', 'tickets'])->findOrFail($id);

        // Add next upcoming reservation info
        $nextReservation = $availability->getNextUpcomingReservation((int) $id);
        if ($nextReservation) {
            $vehicle->setAttribute('next_reservation', [
                'start_date' => $nextReservation->start_date,
                'end_date' => $nextReservation->end_date,
                'user_name' => $nextReservation->user?->name,
            ]);
        }

        return response()->json($vehicle);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $vehicle = Vehicle::findOrFail($id);

        $validated = $request->validate([
            'license_plate' => 'sometimes|string|max:20|unique:vehicles,license_plate,'.$id.',vehicle_id',
            'brand' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'color' => 'nullable|string|max:50',
            'status' => 'sometimes|string|in:available,reserved,maintenance,inactive',
            'current_latitude' => 'nullable|numeric|between:-90,90',
            'current_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $vehicle->update($validated);

        return response()->json($vehicle);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted successfully'], 200);
    }

    /**
     * Get reservations for a specific vehicle.
     */
    public function reservations(string $id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $reservations = $vehicle->reservations()->with('user')->get();

        return response()->json($reservations);
    }

    /**
     * Update vehicle location.
     */
    public function updateLocation(Request $request, string $id)
    {
        $vehicle = Vehicle::findOrFail($id);

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $vehicle->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
            'last_location_update' => now(),
        ]);

        return response()->json($vehicle);
    }
}
