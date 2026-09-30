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

    // Gateway WhatsApp MPWA. Host-nya dari sini, BUKAN setting tenant: kunci
    // API (bisa warisan desa/platform) dikirim ke host ini, jadi host wajib
    // https dan terdaftar di allow-list (MpwaService::baseUrl menolak sisanya).
    'mpwa' => [
        'url' => env('MPWA_API_URL', 'https://mpwa.jabnet.id'),
        'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('MPWA_ALLOWED_HOSTS', 'mpwa.jabnet.id'))))),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
