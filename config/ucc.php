<?php

return [
    'api_key' => env('UCC_API_KEY'),
    'api_url' => env('UCC_API_URL', 'https://api.uccfilings.com'),

    'search_types' => [
        'debtor_name' => 'Debtor Name Search',
        'secured_party' => 'Secured Party Search',
        'filing_number' => 'Filing Number Search',
    ],

    'filing_types' => [
        'UCC1' => 'Initial Financing Statement',
        'UCC3' => 'Amendment/Continuation',
        'UCC5' => 'Correction Statement',
    ],
];
