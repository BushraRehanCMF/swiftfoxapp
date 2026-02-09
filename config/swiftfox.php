<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trial Settings
    |--------------------------------------------------------------------------
    |
    | These settings control the trial period for new accounts.
    |
    */

    'trial' => [
        'days' => (int) env('TRIAL_DAYS', 14),
        'conversation_limit' => (int) env('TRIAL_CONVERSATION_LIMIT', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for WhatsApp Cloud API integration.
    |
    */

    'whatsapp' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v18.0'),
        'api_url' => 'https://graph.facebook.com',
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'app_id' => env('WHATSAPP_APP_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'config_id' => env('WHATSAPP_CONFIG_ID'), // Embedded Signup Configuration ID
        'redirect_uri' => env('WHATSAPP_REDIRECT_URI'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paddle Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Paddle payment integration.
    |
    */

    'paddle' => [
        'vendor_id' => env('PADDLE_VENDOR_ID'),
        'vendor_auth_code' => env('PADDLE_VENDOR_AUTH_CODE'),
        'public_key' => env('PADDLE_PUBLIC_KEY'),
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
    ],

];
