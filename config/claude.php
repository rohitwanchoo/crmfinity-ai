<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anthropic Claude API Configuration
    |--------------------------------------------------------------------------
    */

    'api_key' => env('ANTHROPIC_API_KEY'),

    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),

    'api_version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),

    'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-20250514'),

    'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),

    'timeout' => env('ANTHROPIC_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Available Models
    |--------------------------------------------------------------------------
    | claude-opus-4-20250514     - Most capable, best for complex tasks
    | claude-sonnet-4-20250514   - Balanced performance and speed (recommended)
    | claude-haiku-3-5-20241022  - Fastest, best for simple tasks
    */

    'models' => [
        'opus' => 'claude-opus-4-20250514',
        'sonnet' => 'claude-sonnet-4-20250514',
        'haiku' => 'claude-haiku-3-5-20241022',
    ],

];
