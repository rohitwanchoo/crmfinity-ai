<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o'),  // Use gpt-4o for better accuracy
        'enable_retry' => env('OPENAI_ENABLE_RETRY', true),  // Enable auto-retry on validation errors
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-haiku-4-5'),  // Use Claude Haiku 4.5 for fastest and most cost-effective analysis
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 16000),
    ],

];
