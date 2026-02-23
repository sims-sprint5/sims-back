<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reservations = Reservation::with(['user', 'vehicle'])->get();
        return response()->json($reservations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,user_id',
            'vehicle_id' => 'required|exists:vehicles,vehicle_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'pickup_location' => 'nullable|string|max:255',
            'dropoff_location' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:pending,active,completed,cancelled',
            'total_cost' => 'nullable|numeric|min:0',
        ]);

        $reservation = Reservation::create($validated);
        
        return response()->json($reservation->load(['user', 'vehicle']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $reservation = Reservation::with(['user', 'vehicle', 'tickets'])->findOrFail($id);
        return response()->json($reservation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $reservation = Reservation::findOrFail($id);
        
        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,user_id',
            'vehicle_id' => 'sometimes|exists:vehicles,vehicle_id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'pickup_location' => 'nullable|string|max:255',
            'dropoff_location' => 'nullable|string|max:255',
            'status' => 'sometimes|string|in:pending,active,completed,cancelled',
            'total_cost' => 'nullable|numeric|min:0',
        ]);

        $reservation->update($validated);
        
        return response()->json($reservation->load(['user', 'vehicle']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->delete();
        
        return response()->json(['message' => 'Reserva eliminada correctamente'], 200);
    }

    /**
     * Get reservations by user.
     */
    public function byUser(string $userId)
    {
        $reservations = Reservation::where('user_id', $userId)
            ->with(['vehicle'])
            ->get();
        
        return response()->json($reservations);
    }

    /**
     * Update reservation status.
     */
    public function updateStatus(Request $request, string $id)
    {
        $reservation = Reservation::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|string|in:pending,active,completed,cancelled',
        ]);

        $reservation->update($validated);
        
        return response()->json($reservation);
    }
}
