<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\Stripe\StripeCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripeCheckoutService $stripeCheckoutService): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        $verify = (bool) config('services.stripe.verify_webhook', true);

        if ($verify) {
            try {
                $event = $stripeCheckoutService->verifyWebhookSignature($payload, $signature);
            } catch (UnexpectedValueException|SignatureVerificationException $e) {
                return response()->json(['message' => 'Invalid Stripe webhook payload.'], 400);
            }
        } else {
            // Development/testing mode: parse the payload without verifying signature.
            // ONLY enable this in local/dev and never in production.
            $event = json_decode($payload);
            if (! $event) {
                return response()->json(['message' => 'Invalid JSON payload.'], 400);
            }
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $sessionId = (string) ($session->id ?? '');
            $paymentStatus = (string) ($session->payment_status ?? '');
            $metadata = (array) ($session->metadata ?? []);
            $reservationIdFromMetadata = (int) ($metadata['reservation_id'] ?? 0);
            $hasStripeSessionIdColumn = Schema::hasColumn('reservations', 'stripe_session_id');
            $hasPaidAtColumn = Schema::hasColumn('reservations', 'paid_at');

            if ($sessionId === '') {
                return response()->json(['message' => 'Missing checkout session id.'], 400);
            }

            $reservationQuery = Reservation::query();

            if ($hasStripeSessionIdColumn) {
                $reservationQuery->where('stripe_session_id', $sessionId);
            } elseif ($reservationIdFromMetadata > 0) {
                $reservationQuery->where('reservation_id', $reservationIdFromMetadata);
            } else {
                return response()->json(['message' => 'Reservation reference not found in webhook payload.'], 200);
            }

            $reservation = $reservationQuery->first();

            if (! $reservation) {
                return response()->json(['message' => 'Reservation not found for session.'], 200);
            }

            // Idempotency: webhook retries should not duplicate state transitions.
            if ((string) $reservation->status === 'paid') {
                return response()->json(['message' => 'Reservation already paid.'], 200);
            }

            if ($paymentStatus !== 'paid') {
                return response()->json(['message' => 'Checkout completed without paid status.'], 202);
            }

            $updatePayload = [
                'status' => 'paid',
            ];

            if ($hasPaidAtColumn) {
                $updatePayload['paid_at'] = now();
            }

            Reservation::query()
                ->where('reservation_id', $reservation->reservation_id)
                ->where('status', '!=', 'paid')
                ->update($updatePayload);
        }

        if ($event->type === 'checkout.session.expired') {
            $session = $event->data->object;
            $sessionId = (string) ($session->id ?? '');
            $metadata = (array) ($session->metadata ?? []);
            $reservationIdFromMetadata = (int) ($metadata['reservation_id'] ?? 0);
            $hasStripeSessionIdColumn = Schema::hasColumn('reservations', 'stripe_session_id');

            if ($sessionId !== '') {
                $reservationQuery = Reservation::query();

                if ($hasStripeSessionIdColumn) {
                    $reservationQuery->where('stripe_session_id', $sessionId);
                } elseif ($reservationIdFromMetadata > 0) {
                    $reservationQuery->where('reservation_id', $reservationIdFromMetadata);
                } else {
                    return response()->json(['received' => true]);
                }

                $reservationQuery
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'cancelled',
                    ]);
            }
        }

        return response()->json(['received' => true]);
    }
}
