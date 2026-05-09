<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'verify_webhook' => (bool) env('STRIPE_VERIFY_WEBHOOK', true),
        'currency' => env('STRIPE_CURRENCY', 'eur'),
        'default_reservation_price_eur' => (float) env('STRIPE_DEFAULT_RESERVATION_PRICE_EUR', 49.99),
        'success_url' => env('STRIPE_SUCCESS_URL', rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/').'/payment/success?session_id={CHECKOUT_SESSION_ID}'),
        'cancel_url' => env('STRIPE_CANCEL_URL', rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/').'/payment/cancel'),
    ],

    'reservations' => [
        'hourly_rate_eur' => (float) env('RESERVATION_HOURLY_RATE_EUR', 5.0),
        'rounding' => env('RESERVATION_HOURLY_ROUNDING', 'exact'),
    ],

];
