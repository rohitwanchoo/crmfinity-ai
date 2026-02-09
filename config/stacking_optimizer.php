<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Position Stacking Optimizer Configuration
    |--------------------------------------------------------------------------
    |
    | Configure parameters for calculating optimal MCA funding positions
    | considering existing positions and merchant capacity.
    |
    */

    // Minimum funding amount
    'minimum_funding_amount' => env('MIN_FUNDING_AMOUNT', 5000),

    // Maximum allowed MCA positions
    'maximum_positions' => 4,

    // Maximum burden ratio by risk score
    'burden_ratios' => [
        'excellent' => [
            'min_score' => 80,
            'max_burden' => 50,
        ],
        'good' => [
            'min_score' => 60,
            'max_burden' => 40,
        ],
        'fair' => [
            'min_score' => 40,
            'max_burden' => 30,
        ],
        'poor' => [
            'min_score' => 0,
            'max_burden' => 20,
        ],
    ],

    // Factor rates by risk tier
    'factor_rates' => [
        'tier_1' => [
            'min_score' => 80,
            'rate' => 1.15,
            'max_term' => 12,
            'approval_percentage' => 1.0,
        ],
        'tier_2' => [
            'min_score' => 60,
            'rate' => 1.25,
            'max_term' => 9,
            'approval_percentage' => 0.8,
        ],
        'tier_3' => [
            'min_score' => 40,
            'rate' => 1.35,
            'max_term' => 6,
            'approval_percentage' => 0.6,
        ],
        'tier_4' => [
            'min_score' => 0,
            'rate' => 1.45,
            'max_term' => 4,
            'approval_percentage' => 0.4,
        ],
    ],

    // Stacking premium per existing position
    'stacking_premium_per_position' => 0.03,

    // Position adjustment per existing MCA
    'position_reduction_per_mca' => 0.10, // 10% reduction per position

    // Holdback percentages
    'holdback' => [
        'base' => 0.10,
        'low_score_addition' => 0.03,
        'very_low_score_addition' => 0.03,
        'stacking_addition_per_position' => 0.02,
        'maximum' => 0.25,
    ],

    // Capacity multipliers based on bank analysis
    'capacity_multipliers' => [
        'poor_cash_flow' => 0.5,
        'fair_cash_flow' => 0.75,
        'high_nsf' => 0.7,
    ],

    // Exposure ratio thresholds
    'exposure_thresholds' => [
        'high_risk' => 1.5, // 18 months of revenue
        'medium_risk' => 1.0, // 12 months of revenue
    ],

    // Buyout settings
    'buyout' => [
        'enabled' => true,
        'max_buyout_percentage' => 0.7, // Don't use more than 70% for buyout
        'discount_near_term' => 0.05, // 5% discount for positions ending in 2 months
        'discount_mid_term' => 0.02, // 2% discount for positions ending in 4 months
    ],

    // Known MCA funders (for detection)
    'known_funders' => [
        'ondeck',
        'kabbage',
        'can capital',
        'rapid',
        'bizfi',
        'credibly',
        'national funding',
        'fundbox',
        'bluevine',
        'lendio',
        'fundera',
        'square capital',
        'paypal working capital',
        'shopify capital',
        'stripe capital',
    ],
];
