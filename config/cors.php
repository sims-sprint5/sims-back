<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Allowed origins, methods and headers for cross-origin requests.
    | The CorsMiddleware handles both exact matches and regex patterns.
    |
    */

    'allowed_origins' => array_values(array_unique(array_filter([
        'http://localhost',
        'http://localhost:5173',
        'http://localhost:8000',
        'http://localhost:3000',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:5173',
        env('APP_URL'),
        env('FRONTEND_URL'),
        ...array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    ]))),

    /*
    | Regex patterns matched against the request Origin header.
    | This allows all lvh.me subdomains on any port (5173 dev or 8000 prod).
    */
    'allowed_origins_patterns' => array_values(array_filter([
        '#^https?://[a-z0-9\-]+\.lvh\.me(:\d+)?$#i',
        '#^https?://[a-z0-9\-]+\.nip\.io(:\d+)?$#i',
        ...array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS_PATTERNS', ''))),
    ])),

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
        'HEAD',
    ],

    'allowed_headers' => [
        'Accept',
        'Accept-Encoding',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-Tenant',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
