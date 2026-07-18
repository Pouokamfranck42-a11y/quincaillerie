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

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        // 0 ou vide = illimité. Quota/coût (Phase 7) — voir GeminiUsageLimiter.
        'daily_call_limit' => env('GEMINI_DAILY_CALL_LIMIT', 0),
    ],

    'payment' => [
        // 'simulation' (défaut, aucun agrégateur requis) ou 'aggregator' (réel — voir AggregatorPaymentProvider).
        'mode' => env('PAYMENT_MODE', 'simulation'),
        'simulation_secret' => env('PAYMENT_SIMULATION_SECRET', 'simulation-secret-non-configure'),
        'aggregator' => [
            'base_url' => env('PAYMENT_AGGREGATOR_BASE_URL'),
            'api_key' => env('PAYMENT_AGGREGATOR_API_KEY'),
            'webhook_secret' => env('PAYMENT_AGGREGATOR_WEBHOOK_SECRET'),
        ],
    ],

];
