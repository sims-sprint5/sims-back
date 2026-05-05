<?php

namespace App\Services\Stripe;

use App\Models\Reservation;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeCheckoutService
{
    private StripeClient $client;

    public function __construct()
    {
        $secretKey = (string) config('services.stripe.secret', '');

        if ($secretKey === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $this->client = new StripeClient($secretKey);
    }

    public function createCheckoutSession(Reservation $reservation, float $priceEur): Session
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->buildCheckoutPayloadFromReservation($reservation, $priceEur);

        return $this->client->checkout->sessions->create($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheckoutPayloadFromReservation(Reservation $reservation, float $priceEur): array
    {
        $reservationId = (string) $reservation->getKey();
        $currency = strtolower((string) config('services.stripe.currency', 'eur'));
        $unitAmount = (int) round($priceEur * 100);

        if ($unitAmount <= 0) {
            throw new RuntimeException('Reservation price must be greater than 0.');
        }

        $successUrl = $this->resolveTenantCheckoutUrl((string) config('services.stripe.success_url'));
        $cancelUrl = $this->resolveTenantCheckoutUrl((string) config('services.stripe.cancel_url'));

        $lineItem = $this->buildLineItem($reservationId, $currency, $unitAmount);
        $metadata = $this->buildMetadata($reservation);

        return $this->buildCheckoutPayload($successUrl, $cancelUrl, $lineItem, $metadata);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLineItem(string $reservationId, string $currency, int $unitAmount): array
    {
        return [
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => $unitAmount,
                'product_data' => [
                    'name' => 'Vehicle reservation #'.$reservationId,
                    'description' => 'One-time reservation payment',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetadata(Reservation $reservation): array
    {
        return [
            'reservation_id' => (string) $reservation->getKey(),
            'user_id' => (string) $reservation->user_id,
            'vehicle_id' => (string) $reservation->vehicle_id,
        ];
    }

    /**
     * @param array<string, mixed> $lineItem
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function buildCheckoutPayload(
        string $successUrl,
        string $cancelUrl,
        array $lineItem,
        array $metadata
    ): array {
        return [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'payment_method_types' => ['card'],
            'line_items' => [$lineItem],
            'metadata' => $metadata,
        ];
    }

    public function resolveReservationPrice(): float
    {
        return (float) config('services.stripe.default_reservation_price_eur', 49.99);
    }

    private function resolveTenantCheckoutUrl(string $url): string
    {
        if (! str_contains($url, '{tenant}')) {
            return $url;
        }

        $tenantId = (string) tenant('id');

        if ($tenantId === '') {
            throw new RuntimeException('Tenant context is required to resolve Stripe checkout URLs.');
        }

        return str_replace('{tenant}', $tenantId, $url);
    }

    /**
     * @throws SignatureVerificationException
     * @throws UnexpectedValueException
     */
    public function verifyWebhookSignature(string $payload, ?string $signatureHeader): Event
    {
        $webhookSecret = (string) config('services.stripe.webhook_secret', '');

        if ($webhookSecret === '') {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        if (empty($signatureHeader)) {
            throw new UnexpectedValueException('Missing Stripe-Signature header.');
        }

        return Webhook::constructEvent($payload, $signatureHeader, $webhookSecret);
    }
}
