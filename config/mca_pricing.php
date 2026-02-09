<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCA Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Dynamic MCA Calculator including factor rates,
    | terms, industry adjustments, and credit score impacts.
    |
    */

    // CRITICAL: Maximum total withhold percentage (industry standard)
    // This is a HARD CAP that cannot be exceeded
    'max_withhold_percentage' => env('MCA_MAX_WITHHOLD', 0.20), // 20%

    // Minimum withhold to ensure viable payment structure
    'min_withhold_percentage' => env('MCA_MIN_WITHHOLD', 0.05), // 5%

    // Business days per month for daily payment calculations
    'business_days_per_month' => env('BUSINESS_DAYS_PER_MONTH', 21.67),

    // Minimum funding amount
    'minimum_funding_amount' => env('MCA_MIN_FUNDING', 5000),

    // Maximum funding amount
    'maximum_funding_amount' => env('MCA_MAX_FUNDING', 500000),

    // Maximum number of MCA positions allowed
    'max_positions' => 4,

    /*
    |--------------------------------------------------------------------------
    | Factor Rate Tiers
    |--------------------------------------------------------------------------
    |
    | Factor rates based on risk score. Lower risk = better rates.
    | Risk score is 0-100 where higher is better (less risky).
    |
    */
    'factor_rate_tiers' => [
        'tier_1' => [
            'name' => 'Premium',
            'min_risk_score' => 80,
            'base_factor_rate' => 1.15,
            'max_factor_rate' => 1.25,
            'max_term_months' => 12,
            'approval_percentage' => 1.0, // Can approve up to 100% of requested
        ],
        'tier_2' => [
            'name' => 'Standard',
            'min_risk_score' => 60,
            'base_factor_rate' => 1.20,
            'max_factor_rate' => 1.35,
            'max_term_months' => 9,
            'approval_percentage' => 0.85,
        ],
        'tier_3' => [
            'name' => 'Moderate Risk',
            'min_risk_score' => 40,
            'base_factor_rate' => 1.30,
            'max_factor_rate' => 1.45,
            'max_term_months' => 6,
            'approval_percentage' => 0.70,
        ],
        'tier_4' => [
            'name' => 'High Risk',
            'min_risk_score' => 20,
            'base_factor_rate' => 1.40,
            'max_factor_rate' => 1.55,
            'max_term_months' => 4,
            'approval_percentage' => 0.50,
        ],
        'tier_5' => [
            'name' => 'Very High Risk',
            'min_risk_score' => 0,
            'base_factor_rate' => 1.50,
            'max_factor_rate' => 1.65,
            'max_term_months' => 3,
            'approval_percentage' => 0.30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Score Adjustments
    |--------------------------------------------------------------------------
    |
    | Adjustments to factor rate and max term based on personal credit score.
    |
    */
    'credit_score_adjustments' => [
        'excellent' => [
            'min_score' => 750,
            'factor_adjustment' => -0.05, // Reduce factor by 0.05
            'term_adjustment' => 2, // Add 2 months to max term
            'approval_boost' => 0.10, // Increase approval % by 10%
        ],
        'good' => [
            'min_score' => 680,
            'factor_adjustment' => -0.02,
            'term_adjustment' => 1,
            'approval_boost' => 0.05,
        ],
        'fair' => [
            'min_score' => 620,
            'factor_adjustment' => 0,
            'term_adjustment' => 0,
            'approval_boost' => 0,
        ],
        'poor' => [
            'min_score' => 550,
            'factor_adjustment' => 0.05,
            'term_adjustment' => -1,
            'approval_boost' => -0.10,
        ],
        'very_poor' => [
            'min_score' => 0,
            'factor_adjustment' => 0.10,
            'term_adjustment' => -2,
            'approval_boost' => -0.20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Industry Adjustments
    |--------------------------------------------------------------------------
    |
    | Industry-specific adjustments for risk and terms.
    | Industries are categorized by risk level and seasonality.
    |
    */
    'industry_adjustments' => [
        // LOW RISK INDUSTRIES
        'healthcare' => [
            'risk_level' => 'low',
            'factor_adjustment' => -0.03,
            'term_adjustment' => 1,
            'max_withhold_override' => null, // Use default
            'notes' => 'Stable recurring revenue from insurance',
        ],
        'professional_services' => [
            'risk_level' => 'low',
            'factor_adjustment' => -0.02,
            'term_adjustment' => 1,
            'max_withhold_override' => null,
            'notes' => 'B2B contracts, predictable revenue',
        ],
        'dental' => [
            'risk_level' => 'low',
            'factor_adjustment' => -0.03,
            'term_adjustment' => 1,
            'max_withhold_override' => null,
            'notes' => 'Insurance-backed, recession resistant',
        ],

        // MEDIUM RISK INDUSTRIES
        'retail' => [
            'risk_level' => 'medium',
            'factor_adjustment' => 0,
            'term_adjustment' => 0,
            'max_withhold_override' => null,
            'notes' => 'Varies by type, generally stable',
        ],
        'ecommerce' => [
            'risk_level' => 'medium',
            'factor_adjustment' => 0,
            'term_adjustment' => 0,
            'max_withhold_override' => null,
            'notes' => 'High volume, seasonal variations',
        ],
        'auto_repair' => [
            'risk_level' => 'medium',
            'factor_adjustment' => 0,
            'term_adjustment' => 0,
            'max_withhold_override' => null,
            'notes' => 'Essential service, steady demand',
        ],
        'beauty_salon' => [
            'risk_level' => 'medium',
            'factor_adjustment' => 0.02,
            'term_adjustment' => 0,
            'max_withhold_override' => null,
            'notes' => 'Repeat customers, some seasonality',
        ],

        // MEDIUM-HIGH RISK INDUSTRIES
        'restaurant' => [
            'risk_level' => 'medium_high',
            'factor_adjustment' => 0.05,
            'term_adjustment' => -1,
            'max_withhold_override' => 0.18, // Lower max withhold
            'notes' => 'High failure rate, thin margins, volatile revenue',
        ],
        'bar_nightclub' => [
            'risk_level' => 'medium_high',
            'factor_adjustment' => 0.08,
            'term_adjustment' => -1,
            'max_withhold_override' => 0.15,
            'notes' => 'Highly seasonal, regulatory risk',
        ],
        'food_truck' => [
            'risk_level' => 'medium_high',
            'factor_adjustment' => 0.07,
            'term_adjustment' => -1,
            'max_withhold_override' => 0.15,
            'notes' => 'Weather dependent, mobile risk',
        ],

        // HIGH RISK INDUSTRIES
        'construction' => [
            'risk_level' => 'high',
            'factor_adjustment' => 0.08,
            'term_adjustment' => -2,
            'max_withhold_override' => 0.15,
            'notes' => 'Project-based, seasonal, high volatility',
        ],
        'trucking' => [
            'risk_level' => 'high',
            'factor_adjustment' => 0.10,
            'term_adjustment' => -2,
            'max_withhold_override' => 0.15,
            'notes' => 'Fuel costs, regulatory, high default rate',
        ],
        'landscaping' => [
            'risk_level' => 'high',
            'factor_adjustment' => 0.08,
            'term_adjustment' => -1,
            'max_withhold_override' => 0.15,
            'notes' => 'Highly seasonal in most regions',
        ],

        // VERY HIGH RISK / RESTRICTED INDUSTRIES
        'cannabis' => [
            'risk_level' => 'very_high',
            'factor_adjustment' => 0.15,
            'term_adjustment' => -3,
            'max_withhold_override' => 0.12,
            'notes' => 'Banking restrictions, regulatory uncertainty',
        ],
        'gambling' => [
            'risk_level' => 'very_high',
            'factor_adjustment' => 0.15,
            'term_adjustment' => -3,
            'max_withhold_override' => 0.12,
            'notes' => 'Regulatory risk, volatile revenue',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Position Stacking Adjustments
    |--------------------------------------------------------------------------
    |
    | Adjustments when merchant has existing MCA positions.
    |
    */
    'stacking_adjustments' => [
        // Factor rate premium per existing position
        'factor_premium_per_position' => 0.03,

        // Approval reduction per existing position
        'approval_reduction_per_position' => 0.10,

        // Position-specific adjustments
        'position_adjustments' => [
            1 => [ // First position
                'factor_adjustment' => 0,
                'approval_modifier' => 1.0,
            ],
            2 => [ // Second position
                'factor_adjustment' => 0.05,
                'approval_modifier' => 0.85,
            ],
            3 => [ // Third position
                'factor_adjustment' => 0.10,
                'approval_modifier' => 0.70,
            ],
            4 => [ // Fourth position (max)
                'factor_adjustment' => 0.15,
                'approval_modifier' => 0.50,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Volatility Adjustments
    |--------------------------------------------------------------------------
    |
    | Adjustments based on revenue volatility (coefficient of variation).
    |
    */
    'volatility_adjustments' => [
        'low' => [ // CV < 15%
            'factor_adjustment' => -0.02,
            'term_adjustment' => 1,
            'approval_boost' => 0.05,
        ],
        'medium' => [ // CV 15-30%
            'factor_adjustment' => 0,
            'term_adjustment' => 0,
            'approval_boost' => 0,
        ],
        'high' => [ // CV > 30%
            'factor_adjustment' => 0.05,
            'term_adjustment' => -1,
            'approval_boost' => -0.10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Holdback/Withhold Calculation
    |--------------------------------------------------------------------------
    |
    | Configuration for calculating holdback percentage.
    |
    */
    'holdback' => [
        'base_percentage' => 0.10, // 10% base
        'risk_adjustment' => [
            'low' => -0.02,
            'medium' => 0,
            'high' => 0.03,
            'very_high' => 0.05,
        ],
        'stacking_addition_per_position' => 0.02,
        'minimum' => 0.08, // 8% minimum
        'maximum' => 0.25, // 25% maximum
    ],

    /*
    |--------------------------------------------------------------------------
    | Offer Validation Rules
    |--------------------------------------------------------------------------
    |
    | Server-side validation rules for offer terms.
    |
    */
    'validation' => [
        'factor_rate' => [
            'min' => 1.10,
            'max' => 1.75,
        ],
        'term_months' => [
            'min' => 2,
            'max' => 18,
        ],
        'daily_payment' => [
            'min' => 50,
            'max' => 50000,
        ],
        'holdback_percentage' => [
            'min' => 0.05,
            'max' => 0.30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Offer Explanation Templates
    |--------------------------------------------------------------------------
    |
    | Templates for explaining offer terms to underwriters.
    |
    */
    'explanation_templates' => [
        'approved' => 'Offer approved: :funding_amount at :factor_rate factor for :term_months months. Daily payment of :daily_payment represents :withhold_percent% of daily revenue.',
        'reduced' => 'Offer reduced from :requested to :funding_amount due to: :reasons',
        'declined_capacity' => 'Declined: Merchant at maximum withhold capacity (:current_withhold% of :max_withhold% limit)',
        'declined_positions' => 'Declined: Too many existing positions (:position_count of :max_positions maximum)',
        'declined_risk' => 'Declined: Risk score :risk_score below minimum threshold',
    ],
];
