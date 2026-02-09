<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HubSpot App Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are used for the HubSpot OAuth integration.
    | You can create a HubSpot app at https://developers.hubspot.com/
    |
    */

    'client_id' => env('HUBSPOT_CLIENT_ID'),
    'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
    'redirect_uri' => env('HUBSPOT_REDIRECT_URI', '/hubspot/callback'),

    /*
    |--------------------------------------------------------------------------
    | HubSpot API Settings
    |--------------------------------------------------------------------------
    */

    'api_base_url' => 'https://api.hubapi.com',
    'oauth_base_url' => 'https://app.hubspot.com/oauth/authorize',
    'token_url' => 'https://api.hubapi.com/oauth/v1/token',

    /*
    |--------------------------------------------------------------------------
    | OAuth Scopes
    |--------------------------------------------------------------------------
    |
    | The scopes required for the MCA offer calculator integration.
    |
    */

    'scopes' => [
        'crm.objects.contacts.read',
        'crm.objects.contacts.write',
        'crm.objects.companies.read',
        'crm.objects.companies.write',
        'crm.objects.deals.read',
        'crm.objects.deals.write',
        'crm.schemas.deals.read',
        'crm.schemas.custom.read',
    ],

    /*
    |--------------------------------------------------------------------------
    | CRM Card Settings
    |--------------------------------------------------------------------------
    */

    'crm_card' => [
        'title' => 'MCA Offer Calculator',
        'fetch_url' => env('APP_URL') . '/api/hubspot/crm-card',
    ],

    /*
    |--------------------------------------------------------------------------
    | Deal Pipeline & Stage Mapping
    |--------------------------------------------------------------------------
    |
    | Map MCA offer statuses to HubSpot deal stages.
    |
    */

    'deal_pipeline' => env('HUBSPOT_DEAL_PIPELINE', 'default'),

    'deal_stages' => [
        'pending' => 'qualifiedtobuy',
        'approved' => 'presentationscheduled',
        'funded' => 'closedwon',
        'declined' => 'closedlost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Properties
    |--------------------------------------------------------------------------
    |
    | Custom deal properties to create in HubSpot for MCA data.
    |
    */

    'custom_properties' => [
        'mca_funded_amount' => [
            'name' => 'mca_funded_amount',
            'label' => 'MCA Funded Amount',
            'type' => 'number',
            'fieldType' => 'number',
            'groupName' => 'dealinformation',
        ],
        'mca_factor_rate' => [
            'name' => 'mca_factor_rate',
            'label' => 'MCA Factor Rate',
            'type' => 'number',
            'fieldType' => 'number',
            'groupName' => 'dealinformation',
        ],
        'mca_total_payback' => [
            'name' => 'mca_total_payback',
            'label' => 'MCA Total Payback',
            'type' => 'number',
            'fieldType' => 'number',
            'groupName' => 'dealinformation',
        ],
        'mca_monthly_payment' => [
            'name' => 'mca_monthly_payment',
            'label' => 'MCA Monthly Payment',
            'type' => 'number',
            'fieldType' => 'number',
            'groupName' => 'dealinformation',
        ],
        'mca_daily_payment' => [
            'name' => 'mca_daily_payment',
            'label' => 'MCA Daily Payment',
            'type' => 'number',
            'fieldType' => 'number',
            'groupName' => 'dealinformation',
        ],
        'mca_term_months' => [
            'name' => 'mca_term_months',
            'label' => 'MCA Term (Months)',
            'type' => 'number',
            'fieldType' => 'number',
            'groupName' => 'dealinformation',
        ],
        'mca_true_revenue' => [
            'name' => 'mca_true_revenue',
            'label' => 'MCA True Revenue',
            'type' => 'number',
            'fieldType' => 'number',
            'groupName' => 'dealinformation',
        ],
        'mca_offer_id' => [
            'name' => 'mca_offer_id',
            'label' => 'MCA Offer ID',
            'type' => 'string',
            'fieldType' => 'text',
            'groupName' => 'dealinformation',
        ],
    ],
];
