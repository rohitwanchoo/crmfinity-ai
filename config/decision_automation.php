<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Decision Automation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automated decision-making for MCA applications.
    |
    */

    // Master switch for automation
    'enabled' => env('DECISION_AUTOMATION_ENABLED', true),

    // Auto-approve settings
    'auto_approve_enabled' => env('AUTO_APPROVE_ENABLED', true),
    'auto_approve_threshold' => env('AUTO_APPROVE_THRESHOLD', 80),

    // Auto-decline settings
    'auto_decline_enabled' => env('AUTO_DECLINE_ENABLED', true),
    'auto_decline_threshold' => env('AUTO_DECLINE_THRESHOLD', 30),

    // Required verifications before automated decision
    'required_verifications' => [
        'persona', // Identity verification
        'experian_credit', // Personal credit check
    ],

    // Optional verifications (recommended but not blocking)
    'recommended_verifications' => [
        'experian_business',
        'datamerch',
        'ucc',
        'bank_analysis',
    ],

    // Notification settings
    'notifications_enabled' => env('DECISION_NOTIFICATIONS_ENABLED', true),
    'notify_applicant' => true,
    'notify_underwriters' => true,

    // Auto-assignment settings
    'auto_assign_enabled' => env('AUTO_ASSIGN_ENABLED', true),
    'assignment_method' => 'round_robin', // round_robin, workload, expertise

    // Review level thresholds
    'review_levels' => [
        'senior' => [
            'score_below' => 30,
            'high_flags_min' => 2,
        ],
        'experienced' => [
            'score_below' => 50,
            'high_flags_min' => 1,
        ],
        'standard' => [
            'score_below' => 100,
            'high_flags_min' => 0,
        ],
    ],

    // Maximum batch size for batch processing
    'max_batch_size' => 50,

    // Retry settings for failed automations
    'retry_enabled' => true,
    'max_retries' => 3,
    'retry_delay_minutes' => 5,

    // Audit settings
    'audit_enabled' => true,
    'audit_retention_days' => 365,

    // Performance thresholds (for monitoring)
    'performance' => [
        'max_processing_time_seconds' => 30,
        'alert_on_slow_processing' => true,
    ],
];
