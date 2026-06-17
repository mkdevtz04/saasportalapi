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

    'palmpesa' => [
        'base_url' => env('PALMPESA_BASE_URL', 'https://palmpesa.drmlelwa.co.tz'),
        'key'      => env('PALMPESA_API_KEY'),
        'user_id'  => env('PALMPESA_USER_ID'),
    ],

    'mikrotik' => [
        'ip'       => env('MIKROTIK_IP', '192.168.88.1'),
        'user'     => env('MIKROTIK_USER', 'costa'),
        'password' => env('MIKROTIK_PASSWORD', 'costa123'),
        'port'     => env('MIKROTIK_PORT', 8728),
    ],

];
