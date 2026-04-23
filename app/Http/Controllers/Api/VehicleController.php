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
     * 
     * For normal users: ONLY "available" vehicles (map view)
     * For admin: all vehicles
     */
    public function index(Request $request)
    {
        $availability = app(ReservationAvailabilityService::class);
        $availability->releaseExpiredReservations();

        $perPage = $request->integer('per_page', 15);
        $user = $request->user();
        $query = Vehicle::query()->with(['reservations.user']);

        if (! $this->isAdmin($request)) {
            // Normal users ONLY see available vehicles on the map
            $query->where('status', 'available');
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

    /**
     * Get all vehicles with reservation calendar for the reservations page.
     * Shows all vehicles (available+reserved) with calendar data.
     * 
     * For normal users: no filtering (see all)
     * For admin: see all
     */
    public function allWithCalendar(Request $request)
    {
        $availability = app(ReservationAvailabilityService::class);
        $availability->releaseExpiredReservations();

        $perPage = $request->integer('per_page', 15);
        $query = Vehicle::query()->with(['reservations' => function ($q) {
            $q->whereIn('status', ['pending', 'paid', 'active'])
                ->orderBy('start_date', 'asc');
        }]);

        $vehicles = $query->paginate($perPage);

        // Enrich with calendar and prereservation info
        $vehicles->getCollection()->transform(function (Vehicle $vehicle) use ($availability) {
            // Get all future reservations for calendar
            $futureReservations = $vehicle->reservations
                ->where('end_date', '>=', now()->startOfDay())
                ->sortBy('start_date')
                ->values();

            $blockedDates = [];
            foreach ($futureReservations as $res) {
                $current = $res->start_date->copy()->startOfDay();
                $last = $res->end_date->copy()->startOfDay();

                while ($current->lte($last)) {
                    $blockedDates[$current->toDateString()] = true;
                    $current->addDay();
                }
            }

            $vehicle->setAttribute('calendar_reservations', $futureReservations->map(function ($res) {
                return [
                    'start_date' => $res->start_date->toIso8601String(),
                    'end_date' => $res->end_date->toIso8601String(),
                    'user_name' => $res->user?->name,
                    'status' => $res->status,
                    'calendar_state' => 'occupied',
                ];
            })->toArray());

            $vehicle->setAttribute('blocked_dates', array_keys($blockedDates));

            // Get next available slot
            if ($futureReservations->isNotEmpty()) {
                $lastReservation = $futureReservations->last();
                $vehicle->setAttribute('next_available_at', $lastReservation->end_date->toIso8601String());
            } else {
                $vehicle->setAttribute('next_available_at', now()->toIso8601String());
            }

            return $vehicle;
        });

        return response()->json($vehicles);
    }

    /**
     * Get availability calendar for a specific vehicle.
     * Returns JSON array of {start, end, status}
     */
    public function disponibilitat(string $id)
    {
        $vehicle = Vehicle::findOrFail($id);

        $reservations = $vehicle->reservations()
            ->whereIn('status', ['pending', 'paid', 'active'])
            ->orderBy('start_date', 'asc')
            ->get();

        $data = $reservations->map(function ($res) {
            return [
                'start' => $res->start_date->format('Y-m-d'),
                'end' => $res->end_date->format('Y-m-d'),
                'status' => 'reservat',
            ];
        })->toArray();

        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Sync all vehicles availability (admin only - for maintenance/debug).
     */
    public function syncAllAvailability(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $availability = app(ReservationAvailabilityService::class);
        
        // First release expired reservations
        $availability->releaseExpiredReservations();
        
        // Then sync all vehicles
        $availability->syncAllVehiclesAvailability();

        return response()->json([
            'message' => 'All vehicles synchronized successfully',
        ], 200);
    }
}
