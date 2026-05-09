<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Services\PriceCalculatorService;
use App\Services\ReservationAvailabilityService;
use App\Services\Stripe\StripeCheckoutService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class ReservationCheckoutController extends Controller
{
    public function store(
        Request $request,
        StripeCheckoutService $stripeCheckoutService,
        PriceCalculatorService $priceCalculatorService
    ): JsonResponse {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $availability = app(ReservationAvailabilityService::class);
        $availability->releaseExpiredReservations();

        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,vehicle_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'pickup_location' => 'nullable|string|max:255',
            'dropoff_location' => 'nullable|string|max:255',
        ]);

        $vehicle = Vehicle::query()->findOrFail($validated['vehicle_id']);

        if (! in_array((string) $vehicle->status, ['available', 'reserved'], true)) {
            return response()->json(['message' => 'Vehicle is not available for reservation.'], 422);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

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

        try {
            $price = $priceCalculatorService->calculateReservationPrice($startDate, $endDate);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $reservation = Reservation::create([
                'user_id' => $user->user_id,
                'vehicle_id' => $vehicle->vehicle_id,
                'start_date' => $startDate->utc()->toDateTimeString(),
                'end_date' => $endDate->utc()->toDateTimeString(),
                'pickup_location' => $validated['pickup_location'] ?? null,
                'dropoff_location' => $validated['dropoff_location'] ?? null,
                'status' => 'pending',
                'total_cost' => $price,
            ]);

            $checkoutSession = $stripeCheckoutService->createCheckoutSession($reservation, $price);

            if (Schema::hasColumn('reservations', 'stripe_session_id')) {
                $reservation->update([
                    'stripe_session_id' => $checkoutSession->id,
                ]);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message' => 'Unable to initialize payment session. Please try again.',
            ], 500);
        }

        $availability->syncVehicleAvailability((int) $reservation->vehicle_id);

        return response()->json([
            'reservation_id' => $reservation->reservation_id,
            'status' => $reservation->status,
            // Keep `price` key for API backward compatibility.
            'price' => $reservation->total_cost,
            'total_cost' => $reservation->total_cost,
            'currency' => strtolower((string) config('services.stripe.currency', 'eur')),
            'checkout_url' => $checkoutSession->url,
            'stripe_session_id' => $checkoutSession->id,
        ], 201);
    }
}
