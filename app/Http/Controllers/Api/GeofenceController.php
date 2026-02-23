<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use App\Models\Vehicle;
use App\Models\VehicleGeofenceLog;
use Illuminate\Http\Request;

class GeofenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $geofences = Geofence::all();
        return response()->json($geofences);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'type' => 'required|string|in:allowed,restricted,parking,service_area',
            'center_latitude' => 'required|numeric|between:-90,90',
            'center_longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|integer|min:1',
            'polygon_coordinates' => 'nullable|array',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $geofence = Geofence::create($validated);
        
        return response()->json($geofence, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $geofence = Geofence::with(['vehicleLogs.vehicle'])->findOrFail($id);
        return response()->json($geofence);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $geofence = Geofence::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'type' => 'sometimes|string|in:allowed,restricted,parking,service_area',
            'center_latitude' => 'sometimes|numeric|between:-90,90',
            'center_longitude' => 'sometimes|numeric|between:-180,180',
            'radius' => 'sometimes|integer|min:1',
            'polygon_coordinates' => 'nullable|array',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $geofence->update($validated);
        
        return response()->json($geofence);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $geofence = Geofence::findOrFail($id);
        $geofence->delete();
        
        return response()->json(['message' => 'Geofence eliminada correctamente'], 200);
    }

    /**
     * Get logs for a specific geofence.
     */
    public function logs(string $id)
    {
        $geofence = Geofence::findOrFail($id);
        $logs = $geofence->vehicleLogs()->with('vehicle')->orderBy('event_timestamp', 'desc')->get();
        
        return response()->json($logs);
    }

    /**
     * Check if a vehicle is within a geofence.
     */
    public function checkVehicle(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,vehicle_id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        $geofences = Geofence::where('status', 'active')->get();
        
        $insideGeofences = [];
        
        foreach ($geofences as $geofence) {
            $distance = $this->calculateDistance(
                $validated['latitude'],
                $validated['longitude'],
                $geofence->center_latitude,
                $geofence->center_longitude
            );
            
            if ($distance <= $geofence->radius) {
                $insideGeofences[] = $geofence;
                
                // Log the event
                VehicleGeofenceLog::create([
                    'vehicle_id' => $vehicle->vehicle_id,
                    'geofence_id' => $geofence->geofence_id,
                    'event_type' => $geofence->type === 'restricted' ? 'violation' : 'entry',
                    'event_timestamp' => now(),
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                ]);
            }
        }
        
        return response()->json([
            'vehicle' => $vehicle,
            'inside_geofences' => $insideGeofences,
        ]);
    }

    /**
     * Calculate distance between two coordinates in meters.
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // metros
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
}
