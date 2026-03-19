<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
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
        $perPage = $request->integer('per_page', 15);
        $query = Reservation::query()->with(['user', 'vehicle']);

        if (! $this->isAdmin($request)) {
            $query->where('user_id', $request->user()->user_id);
        }

        $reservations = $query->paginate($perPage);

        return response()->json($reservations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,user_id',
            'vehicle_id' => 'required|exists:vehicles,vehicle_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'pickup_location' => 'nullable|string|max:255',
            'dropoff_location' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:pending,active,completed,cancelled',
            'total_cost' => 'nullable|numeric|min:0',
        ]);

        if (! $this->isAdmin($request)) {
            $validated['user_id'] = $request->user()->user_id;
            unset($validated['status']);
        } else {
            // Admin must specify the user_id explicitly
            if (empty($validated['user_id'])) {
                return response()->json(['message' => 'user_id is required for admin.'], 422);
            }
        }

        $reservation = Reservation::create($validated);

        return response()->json($reservation->load(['user', 'vehicle']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $reservation = Reservation::with(['user', 'vehicle', 'tickets'])->findOrFail($id);

        if (! request()->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->isAdmin(request()) && $reservation->user_id !== request()->user()->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($reservation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $reservation = Reservation::findOrFail($id);

        if (! $this->isAdmin($request) && $reservation->user_id !== $request->user()->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

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

        if (! $this->isAdmin($request)) {
            unset($validated['user_id'], $validated['status']);
        }

        $reservation->update($validated);

        return response()->json($reservation->load(['user', 'vehicle']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $reservation = Reservation::findOrFail($id);

        if (! request()->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->isAdmin(request()) && $reservation->user_id !== request()->user()->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $reservation->delete();

        return response()->json(['message' => 'Reservation deleted successfully'], 200);
    }

    /**
     * Get reservations by user.
     */
    public function byUser(string $userId)
    {
        $request = request();

        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->isAdmin($request) && (string) $request->user()->user_id !== (string) $userId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

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
