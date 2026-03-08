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
    | Stripe Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Stripe payment integration.
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'plans' => [
            'starter' => [
                'price_id' => env('STRIPE_PRICE_ID_STARTER'),
                'name' => 'Starter',
                'price' => 25,
                'currency' => 'USD',
                'description' => 'Perfect for small businesses',
                'conversation_limit' => 500,
                'features' => [
                    '500 conversations/month',
                    'WhatsApp Business integration',
                    'Shared inbox',
                    'Labels & organization',
                    'Basic automations',
                    'Business hours',
                    'Email support',
                ],
            ],
            'pro' => [
                'price_id' => env('STRIPE_PRICE_ID_PRO'),
                'name' => 'Pro',
                'price' => 49,
                'currency' => 'USD',
                'description' => 'For growing teams',
                'conversation_limit' => 2000,
                'popular' => true,
                'features' => [
                    '2,000 conversations/month',
                    'Everything in Starter',
                    'Advanced automations',
                    'Team collaboration',
                    'Webhooks',
                    'Priority support',
                    'Custom labels',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Mode Settings
    |--------------------------------------------------------------------------
    |
    | When enabled, allows testing without requiring WhatsApp connection.
    |
    */

    'demo_mode' => (bool) env('DEMO_MODE', false),

];
