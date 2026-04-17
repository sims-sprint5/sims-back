<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Services\ReservationAvailabilityService;
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
        app(ReservationAvailabilityService::class)->releaseExpiredReservations();

        $perPage = $request->integer('per_page', 15);
        $query = Reservation::query()->with(['user', 'vehicle']);

        if (! $this->isAdmin($request)) {
            $query->where('user_id', $request->user()->user_id);
        }

        $reservations = $query->paginate($perPage);
        $noticeMinutes = max(1, (int) env('RESERVATION_RENEWAL_NOTICE_MINUTES', 15));

        $reservations->getCollection()->transform(function (Reservation $reservation) use ($noticeMinutes) {
            return $this->addRenewalMeta($reservation, $noticeMinutes);
        });

        return response()->json($reservations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $availability = app(ReservationAvailabilityService::class);
        $availability->releaseExpiredReservations();

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

        $vehicle = Vehicle::query()->findOrFail($validated['vehicle_id']);

        if (! in_array((string) $vehicle->status, ['available', 'reserved'], true)) {
            return response()->json(['message' => 'Vehicle is not available for reservation.'], 422);
        }

        // Check for availability in the requested period
        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['end_date']);

        $availabilityCheck = $availability->checkAvailabilityForPeriod(
            (int) $vehicle->vehicle_id,
            $startDate,
            $endDate
        );

        if (! $availabilityCheck['available']) {
            return response()->json([
                'message' => $availabilityCheck['message'],
                'available_at' => $availabilityCheck['available_at'] ?? null,
                'conflicting_reservation' => $availabilityCheck['conflicting_reservation'] ?? null,
            ], 409);
        }

        if (empty($validated['status'])) {
            $validated['status'] = now()->gte($validated['start_date']) ? 'active' : 'pending';
        }

        $reservation = Reservation::create($validated);

        $availability->syncVehicleAvailability((int) $reservation->vehicle_id);

        $noticeMinutes = max(1, (int) env('RESERVATION_RENEWAL_NOTICE_MINUTES', 15));
        $reservation = $this->addRenewalMeta($reservation, $noticeMinutes);

        return response()->json($reservation->load(['user', 'vehicle']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        app(ReservationAvailabilityService::class)->releaseExpiredReservations();

        $reservation = Reservation::with(['user', 'vehicle', 'tickets'])->findOrFail($id);

        if (! request()->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->isAdmin(request()) && $reservation->user_id !== request()->user()->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $noticeMinutes = max(1, (int) env('RESERVATION_RENEWAL_NOTICE_MINUTES', 15));

        return response()->json($this->addRenewalMeta($reservation, $noticeMinutes));
    }

    /**
     * Update the specified resource in storage.
     *
     * Users can only modify: end_date, pickup_location, dropoff_location
     * Admin can modify: all fields
     */
    public function update(Request $request, string $id)
    {
        $availability = app(ReservationAvailabilityService::class);
        $availability->releaseExpiredReservations();

        $reservation = Reservation::findOrFail($id);
        $originalVehicleId = (int) $reservation->vehicle_id;

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

        // Non-admin users: restrict what they can modify
        if (! $this->isAdmin($request)) {
            // Users can only modify: end_date, pickup_location, dropoff_location
            $allowed = ['end_date', 'pickup_location', 'dropoff_location'];
            foreach ($validated as $key => $value) {
                if (! in_array($key, $allowed, true)) {
                    unset($validated[$key]);
                }
            }
        }

        $targetVehicleId = (int) ($validated['vehicle_id'] ?? $reservation->vehicle_id);
        $startDate = isset($validated['start_date'])
            ? $validated['start_date']
            : $reservation->start_date;
        $endDate = isset($validated['end_date'])
            ? $validated['end_date']
            : $reservation->end_date;

        // Check availability for the requested period if vehicle or dates changed
        if ($targetVehicleId !== $originalVehicleId ||
            isset($validated['start_date']) ||
            isset($validated['end_date'])) {

            $availabilityCheck = $availability->checkAvailabilityForPeriod(
                $targetVehicleId,
                $startDate,
                $endDate,
                (int) $reservation->reservation_id
            );

            if (! $availabilityCheck['available']) {
                return response()->json([
                    'message' => $availabilityCheck['message'],
                    'available_at' => $availabilityCheck['available_at'] ?? null,
                    'conflicting_reservation' => $availabilityCheck['conflicting_reservation'] ?? null,
                ], 409);
            }
        }

        $reservation->update($validated);

        $availability->syncVehicleAvailability($originalVehicleId);

        if ($targetVehicleId !== $originalVehicleId) {
            $availability->syncVehicleAvailability($targetVehicleId);
        }

        $noticeMinutes = max(1, (int) env('RESERVATION_RENEWAL_NOTICE_MINUTES', 15));
        $reservation = $this->addRenewalMeta($reservation, $noticeMinutes);

        return response()->json($reservation->load(['user', 'vehicle']));
    }

    /**
     * Remove the specified resource from storage.
     *
     * Admin: Can delete any reservation at any time (soft delete)
     * Users: Can only delete pending reservations that haven't started yet
     */
    public function destroy(string $id)
    {
        $availability = app(ReservationAvailabilityService::class);
        $availability->releaseExpiredReservations();

        $user = request()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $reservation = Reservation::findOrFail($id);
        $vehicleId = (int) $reservation->vehicle_id;

        // Check permissions: user can only delete their own reservations, admin can delete any
        if (! $this->isAdmin(request()) && (int) $reservation->user_id !== (int) $user->user_id) {
            return response()->json(['message' => 'Forbidden. You cannot delete this reservation.'], 403);
        }

        // Non-admin users have restrictions
        if (! $this->isAdmin(request())) {
            // Check if reservation has already started - cannot cancel active/in-progress reservations
            if ((string) $reservation->status === 'active' || $reservation->start_date <= now()) {
                return response()->json([
                    'message' => 'Cannot cancel a reservation that has already started.',
                    'reservation_status' => $reservation->status,
                    'start_date' => $reservation->start_date->toIso8601String(),
                ], 422);
            }

            // Check if reservation is already completed
            if ((string) $reservation->status === 'completed') {
                return response()->json([
                    'message' => 'Cannot cancel a completed reservation.',
                ], 422);
            }

            // Check if reservation is already cancelled
            if ((string) $reservation->status === 'cancelled') {
                return response()->json([
                    'message' => 'This reservation is already cancelled.',
                ], 422);
            }
        }
        // Admin can delete any reservation (no restrictions)

        // Delete the reservation (soft delete)
        $deleted = $reservation->delete();

        if ($deleted) {
            // Sync vehicle availability after deletion
            $availability->syncVehicleAvailability($vehicleId);

            $userRole = $this->isAdmin(request()) ? 'admin' : 'user';

            return response()->json([
                'message' => 'Reservation deleted successfully',
                'vehicle_id' => $vehicleId,
                'deleted_by' => $userRole,
                'deleted_at' => $reservation->deleted_at->toIso8601String(),
            ], 200);
        }

        return response()->json([
            'message' => 'Failed to delete reservation',
        ], 500);
    }

    /**
     * Get reservations by user.
     */
    public function byUser(string $userId)
    {
        app(ReservationAvailabilityService::class)->releaseExpiredReservations();

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

        $noticeMinutes = max(1, (int) env('RESERVATION_RENEWAL_NOTICE_MINUTES', 15));
        $reservations->transform(function (Reservation $reservation) use ($noticeMinutes) {
            return $this->addRenewalMeta($reservation, $noticeMinutes);
        });

        return response()->json($reservations);
    }

    /**
     * Update reservation status.
     */
    public function updateStatus(Request $request, string $id)
    {
        $availability = app(ReservationAvailabilityService::class);
        $availability->releaseExpiredReservations();

        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:pending,active,completed,cancelled',
        ]);

        $reservation->update($validated);

        $availability->syncVehicleAvailability((int) $reservation->vehicle_id);

        $noticeMinutes = max(1, (int) env('RESERVATION_RENEWAL_NOTICE_MINUTES', 15));

        return response()->json($this->addRenewalMeta($reservation, $noticeMinutes));
    }

    public function renewalIntent(Request $request, string $id)
    {
        $reservation = Reservation::findOrFail($id);

        if (! $this->isAdmin($request) && $reservation->user_id !== $request->user()->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'message' => 'Renewal requires payment flow integration.',
            'reservation_id' => $reservation->reservation_id,
            'payment_url' => "/payments/reservations/{$reservation->reservation_id}/renew",
        ]);
    }

    /**
     * Check vehicle availability for a specific date/time period.
     *
     * Query params:
     * - vehicle_id: ID del vehículo (requerido)
     * - start_date: Fecha/hora de inicio (requerido)
     * - end_date: Fecha/hora de fin (requerido)
     * - exclude_reservation_id: ID de reserva a ignorar (opcional, para extender reservas existentes)
     *
     * Response: {available: bool, message?: string, available_at?: string, ...}
     */
    public function checkAvailability(Request $request)
    {
        app(ReservationAvailabilityService::class)->releaseExpiredReservations();

        $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,vehicle_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'exclude_reservation_id' => 'nullable|integer|exists:reservations,reservation_id',
        ]);

        $vehicleId = $request->integer('vehicle_id');
        $startDate = $request->string('start_date');
        $endDate = $request->string('end_date');
        $excludeReservationId = $request->integer('exclude_reservation_id');

        $availability = app(ReservationAvailabilityService::class);
        $result = $availability->checkAvailabilityForPeriod(
            $vehicleId,
            $startDate,
            $endDate,
            $excludeReservationId
        );

        return response()->json($result);
    }

    private function addRenewalMeta(Reservation $reservation, int $noticeMinutes): Reservation
    {
        $now = now();
        $endDate = $reservation->end_date;
        $isReservingStatus = in_array((string) $reservation->status, ['pending', 'active'], true);

        $isExpired = $endDate ? $endDate->lte($now) : false;
        $isActiveWindow = $isReservingStatus && $endDate && $endDate->gt($now);
        $isExpiringSoon = $isActiveWindow && $endDate->lte($now->copy()->addMinutes($noticeMinutes));
        $minutesRemaining = $endDate ? max(0, $now->diffInMinutes($endDate, false)) : null;

        $reservation->setAttribute('is_expired', $isExpired);
        $reservation->setAttribute('minutes_remaining', $minutesRemaining);
        $reservation->setAttribute('can_renew', $isActiveWindow);
        $reservation->setAttribute('renewal_payment_url', $isActiveWindow ? "/payments/reservations/{$reservation->reservation_id}/renew" : null);
        $reservation->setAttribute('renewal_notice', $isExpiringSoon ? 'Tu reserva está por finalizar. Puedes ampliar el tiempo desde la pasarela de pago.' : null);

        return $reservation;
    }
}
