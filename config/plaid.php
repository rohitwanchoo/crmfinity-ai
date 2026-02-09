<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plaid API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Plaid API integration. Get your credentials from
    | https://dashboard.plaid.com/
    |
    */

    'client_id' => env('PLAID_CLIENT_ID'),
    'secret' => env('PLAID_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Supported: "sandbox", "development", "production"
    |
    */
    'env' => env('PLAID_ENV', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | API Base URLs
    |--------------------------------------------------------------------------
    */
    'base_urls' => [
        'sandbox' => 'https://sandbox.plaid.com',
        'development' => 'https://development.plaid.com',
        'production' => 'https://production.plaid.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    */
    'webhook_url' => env('PLAID_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    |
    | Available products: transactions, auth, identity, assets, investments,
    | liabilities, payment_initiation, deposit_switch, income_verification,
    | transfer, employment, signal
    |
    */
    'products' => explode(',', env('PLAID_PRODUCTS', 'transactions,auth,identity')),

    /*
    |--------------------------------------------------------------------------
    | Country Codes
    |--------------------------------------------------------------------------
    |
    | ISO 3166-1 alpha-2 country codes
    |
    */
    'country_codes' => explode(',', env('PLAID_COUNTRY_CODES', 'US')),

    /*
    |--------------------------------------------------------------------------
    | Language
    |--------------------------------------------------------------------------
    */
    'language' => env('PLAID_LANGUAGE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Transaction Sync Settings
    |--------------------------------------------------------------------------
    */
    'transactions' => [
        'days_requested' => 730, // Up to 2 years of transactions
        'include_personal_finance_category' => true,
    ],
];
