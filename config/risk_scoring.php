<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Risk Scoring Weights
    |--------------------------------------------------------------------------
    |
    | Define the weight of each component in the overall risk score calculation.
    | Weights should sum to 1.0 for normalized scoring.
    |
    */

    'weights' => [
        'credit_score' => 0.25,
        'bank_analysis' => 0.25,
        'identity_verification' => 0.15,
        'stacking_check' => 0.15,
        'ucc_filings' => 0.10,
        'industry_risk' => 0.10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Score Ranges
    |--------------------------------------------------------------------------
    |
    | Map credit score ranges to normalized scores (0-100).
    |
    */

    'credit_score_ranges' => [
        'excellent' => ['min' => 750, 'max' => 850, 'score' => 100],
        'good' => ['min' => 700, 'max' => 749, 'score' => 80],
        'fair' => ['min' => 650, 'max' => 699, 'score' => 60],
        'poor' => ['min' => 550, 'max' => 649, 'score' => 40],
        'very_poor' => ['min' => 300, 'max' => 549, 'score' => 20],
    ],

    /*
    |--------------------------------------------------------------------------
    | Industry Risk Levels
    |--------------------------------------------------------------------------
    |
    | Categorize industries by risk level for scoring purposes.
    |
    */

    'industry_risk_levels' => [
        'low' => [
            'Healthcare', 'Professional Services', 'Technology', 'Education',
            'Manufacturing', 'Wholesale Trade', 'Information Technology',
            'Accounting', 'Legal Services', 'Engineering',
        ],
        'medium' => [
            'Retail Trade', 'Transportation', 'Real Estate', 'Construction',
            'Food Services', 'Accommodation', 'Auto Repair', 'Landscaping',
            'Cleaning Services', 'Personal Services',
        ],
        'high' => [
            'Gambling', 'Adult Entertainment', 'Cannabis', 'Firearms',
            'Cryptocurrency', 'Telemarketing', 'Payday Lending',
            'Debt Collection', 'Money Services', 'Pawn Shops',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Decision Thresholds
    |--------------------------------------------------------------------------
    |
    | Score thresholds that determine automated decisions.
    |
    */

    'decision_thresholds' => [
        'auto_approve' => env('RISK_AUTO_APPROVE_THRESHOLD', 80),
        'manual_review' => env('RISK_MANUAL_REVIEW_THRESHOLD', 50),
        'auto_decline' => env('RISK_AUTO_DECLINE_THRESHOLD', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bank Analysis Scoring
    |--------------------------------------------------------------------------
    |
    | Parameters for bank statement analysis scoring.
    |
    */

    'bank_analysis' => [
        'base_score' => 50,
        'revenue_consistency' => [
            'excellent' => ['min' => 0.8, 'bonus' => 20],
            'good' => ['min' => 0.6, 'bonus' => 10],
            'poor' => ['max' => 0.4, 'penalty' => -10],
        ],
        'daily_balance' => [
            'high' => ['min' => 10000, 'bonus' => 15],
            'medium' => ['min' => 5000, 'bonus' => 10],
            'low' => ['max' => 1000, 'penalty' => -15],
        ],
        'nsf_penalty_per_occurrence' => 5,
        'nsf_max_penalty' => 25,
        'negative_days_penalty_per_day' => 2,
        'negative_days_max_penalty' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud Impact on Scoring
    |--------------------------------------------------------------------------
    |
    | How fraud detection affects the overall risk score.
    |
    */

    'fraud_impact' => [
        'enabled' => true,
        'score_threshold' => 60, // Apply penalty if fraud score below this
        'penalty_multiplier' => 0.3, // (100 - fraud_score) * multiplier
    ],

    /*
    |--------------------------------------------------------------------------
    | Offer Term Calculation
    |--------------------------------------------------------------------------
    |
    | Parameters for calculating offer terms based on risk score.
    |
    */

    'offer_terms' => [
        'tiers' => [
            'tier_1' => [
                'min_score' => 80,
                'factor_rate' => 1.15,
                'max_term_months' => 12,
                'approval_percentage' => 1.0,
                'holdback' => 0.10,
            ],
            'tier_2' => [
                'min_score' => 60,
                'factor_rate' => 1.25,
                'max_term_months' => 9,
                'approval_percentage' => 0.8,
                'holdback' => 0.12,
            ],
            'tier_3' => [
                'min_score' => 40,
                'factor_rate' => 1.35,
                'max_term_months' => 6,
                'approval_percentage' => 0.6,
                'holdback' => 0.15,
            ],
            'tier_4' => [
                'min_score' => 0,
                'factor_rate' => 1.45,
                'max_term_months' => 4,
                'approval_percentage' => 0.4,
                'holdback' => 0.18,
            ],
        ],
        'business_days_per_month' => 22,
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk Flags
    |--------------------------------------------------------------------------
    |
    | Automatic flags triggered by specific conditions.
    |
    */

    'auto_flags' => [
        'very_low_credit' => [
            'condition' => 'credit_score < 500',
            'message' => 'Very low credit score',
            'severity' => 'high',
        ],
        'high_stacking' => [
            'condition' => 'active_mcas >= 3',
            'message' => 'Multiple active MCA positions',
            'severity' => 'high',
        ],
        'new_business' => [
            'condition' => 'time_in_business_months < 6',
            'message' => 'Business less than 6 months old',
            'severity' => 'medium',
        ],
        'low_revenue' => [
            'condition' => 'monthly_revenue < 10000',
            'message' => 'Monthly revenue below $10,000',
            'severity' => 'medium',
        ],
        'high_nsf' => [
            'condition' => 'nsf_count > 5',
            'message' => 'High NSF/overdraft frequency',
            'severity' => 'high',
        ],
        'revenue_decline' => [
            'condition' => 'revenue_trend_percentage < -20',
            'message' => 'Significant revenue decline',
            'severity' => 'medium',
        ],
    ],
];
