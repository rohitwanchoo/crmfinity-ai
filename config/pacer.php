<?php

return [
    'username' => env('PACER_USERNAME'),
    'password' => env('PACER_PASSWORD'),
    'client_code' => env('PACER_CLIENT_CODE', ''),
    'base_url' => env('PACER_BASE_URL', 'https://pcl.uscourts.gov/pcl-public-api/rest'),

    'default_court' => env('PACER_DEFAULT_COURT', ''),
    'search_type' => env('PACER_SEARCH_TYPE', 'all'),
    'max_results' => env('PACER_MAX_RESULTS', 100),

    'search_types' => [
        'all' => 'All Courts',
        'bankruptcy' => 'Bankruptcy Courts',
        'civil' => 'Civil Courts',
        'criminal' => 'Criminal Courts',
        'appellate' => 'Appellate Courts',
    ],

    'court_types' => [
        'bk' => 'Bankruptcy',
        'cv' => 'Civil',
        'cr' => 'Criminal',
        'ap' => 'Appellate',
    ],
];
