<?php

return [
    'api_key' => env('DATAMERCH_API_KEY'),
    'env' => env('DATAMERCH_ENV', 'sandbox'),

    'base_urls' => [
        'sandbox' => 'https://sandbox.datamerch.com/api/v1',
        'production' => 'https://api.datamerch.com/v1',
    ],

    'stacking_thresholds' => [
        'high_risk' => 3,      // 3+ active MCAs = high risk
        'medium_risk' => 2,    // 2 active MCAs = medium risk
        'low_risk' => 1,       // 1 active MCA = low risk
    ],
];
