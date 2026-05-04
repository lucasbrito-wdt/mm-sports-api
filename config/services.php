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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mm_store' => [
        'origin_postal_code' => env('MM_STORE_POSTAL_CODE', '58200230'),
    ],

    'asaas' => [
        'api_key' => env('ASAAS_API_KEY'),
        'base_url' => env('ASAAS_BASE_URL', 'https://api.asaas.com/v3'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    ],

    'correios' => [
        'username' => env('CORREIOS_USERNAME'),
        'posting_card' => env('CORREIOS_POSTING_CARD'),
        'service_codes' => ['03220', '04014'], // PAC, SEDEX — adjust to contract
    ],

    'ga4' => [
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
        'api_secret' => env('GA4_API_SECRET'),
    ],

    'meta' => [
        'pixel_id' => env('META_PIXEL_ID'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'test_event_code' => env('META_TEST_EVENT_CODE'),
    ],

];
