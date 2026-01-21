<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fraud Detection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure thresholds and rules for fraud detection in bank statements
    | and application data.
    |
    */

    // Duplicate transaction detection
    'duplicate_threshold' => 5,

    // Round number deposit detection (ratio of round deposits to total)
    'round_number_ratio' => 0.5,
    'round_number_minimum_count' => 5,

    // Structuring detection thresholds (deposits just under reporting limits)
    'structuring_thresholds' => [10000, 5000, 3000],
    'structuring_margin' => 0.05, // Within 5% of threshold

    // Statement gap detection (days without activity)
    'gap_days_threshold' => 7,

    // Deposit velocity (standard deviations for outlier detection)
    'velocity_std_deviation' => 3,

    // Weekend transaction thresholds
    'weekend_ratio_threshold' => 0.3,
    'weekend_minimum_count' => 10,

    // Revenue manipulation patterns
    'personal_deposit_patterns' => [
        'personal',
        'savings',
        'from checking',
        'my account',
        'self',
    ],

    // Fake revenue indicators
    'loan_patterns' => [
        'loan',
        'advance',
        'funding',
        'credit line',
        'loc ',
        'sba',
    ],

    'refund_patterns' => [
        'refund',
        'return',
        'reversal',
        'credit back',
    ],

    // Score impact weights
    'score_impacts' => [
        'duplicate_transactions' => [
            'threshold' => 5,
            'high_impact' => 25,
            'medium_impact' => 10,
        ],
        'round_number_deposits' => [
            'high_impact' => 20,
            'medium_impact' => 10,
        ],
        'suspicious_timing' => [
            'high_impact' => 15,
            'medium_impact' => 8,
        ],
        'deposit_velocity' => [
            'high_impact' => 15,
            'medium_impact' => 8,
        ],
        'structured_deposits' => [
            'high_impact' => 25,
            'medium_impact' => 12,
        ],
        'unusual_patterns' => [
            'high_impact' => 20,
            'medium_impact' => 10,
        ],
        'revenue_manipulation' => [
            'high_impact' => 25,
            'medium_impact' => 12,
        ],
        'fake_revenue_indicators' => [
            'high_impact' => 20,
            'medium_impact' => 10,
        ],
        'statement_gaps' => [
            'high_impact' => 15,
            'medium_impact' => 8,
        ],
        'weekend_anomalies' => [
            'high_impact' => 15,
            'medium_impact' => 8,
        ],
    ],

    // Decision thresholds
    'decision_thresholds' => [
        'decline' => 40,
        'manual_review' => 60,
        'proceed_with_caution' => 80,
    ],

    // Cross-reference variance thresholds
    'revenue_variance_high' => 50, // Percent
    'revenue_variance_medium' => 25, // Percent
];
