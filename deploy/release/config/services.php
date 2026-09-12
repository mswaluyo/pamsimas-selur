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

    // Konfigurasi PAMSIMAS
    'device_api_key' => env('DEVICE_API_KEY', 'P4mS1m4s-T1rt0-Arg0-2025'),
    'wa_gateway_url' => env('WA_GATEWAY_URL', 'http://127.0.0.1:3000/send-wa'),
    'wa_gateway_secret' => env('WA_GATEWAY_SECRET', 'P4mS1m4s-T1rt0-Arg0-2025'),
    'wa_webhook_url' => env('WA_WEBHOOK_URL', 'http://127.0.0.1:8000/api/api_wa'),
    'wa_gateway_number' => env('WA_GATEWAY_NUMBER'),
    'gemini_api_key' => env('GEMINI_API_KEY'),

];
