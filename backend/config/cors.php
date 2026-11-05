<?php

declare(strict_types=1);

/*
| CORS eksplisit: hanya origin frontend yang terdaftar di FRONTEND_URL (bisa lebih dari satu,
| dipisah koma). TIDAK memakai "*".
*/
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key', 'X-Request-Id'],

    'exposed_headers' => ['X-Request-Id', 'Idempotent-Replayed', 'Retry-After'],

    'max_age' => 3600,

    // Bearer token di header, bukan cookie → tidak perlu credentials.
    'supports_credentials' => false,
];
