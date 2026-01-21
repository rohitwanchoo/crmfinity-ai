<?php

return [
    'api_key' => env('PERSONA_API_KEY'),
    'api_version' => env('PERSONA_API_VERSION', '2023-01-05'),
    'template_id' => env('PERSONA_TEMPLATE_ID'),
    'env' => env('PERSONA_ENV', 'sandbox'),
    'webhook_secret' => env('PERSONA_WEBHOOK_SECRET'),

    'base_url' => 'https://withpersona.com/api/v1',

    'inquiry_types' => [
        'government_id' => 'Government ID Verification',
        'selfie' => 'Selfie Verification',
        'database' => 'Database Verification',
    ],
];
