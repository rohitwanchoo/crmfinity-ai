<?php

return [
    'client_id' => env('EXPERIAN_CLIENT_ID'),
    'client_secret' => env('EXPERIAN_CLIENT_SECRET'),
    'username' => env('EXPERIAN_USERNAME'),
    'password' => env('EXPERIAN_PASSWORD'),
    'subscriber_code' => env('EXPERIAN_SUBSCRIBER_CODE'),
    'env' => env('EXPERIAN_ENV', 'sandbox'),

    'base_urls' => [
        'sandbox' => 'https://sandbox-us-api.experian.com',
        'production' => 'https://us-api.experian.com',
    ],

    'products' => [
        'credit_report' => 'Credit Report',
        'credit_score' => 'Credit Score',
        'business_report' => 'Business Credit Report',
        'background_check' => 'Background Check',
    ],
];
