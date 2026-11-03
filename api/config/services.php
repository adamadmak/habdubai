<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'http' => [
        // Optional: a CA bundle file for all outgoing HTTPS. Without it the
        // system list is used, or Mozilla's bundle from composer/ca-bundle.
        'ca_bundle' => env('HTTP_CA_BUNDLE'),
    ],

    'telegram' => [
        // The admin's Settings page is the normal home for the token; these
        // are only for a local .env during development.
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'timeout' => env('TELEGRAM_TIMEOUT', 20),
        'ca_bundle' => env('TELEGRAM_CA_BUNDLE'),
    ],

    /*
     * Host secrets. On this host the environment is visible only while the
     * app builds (the config cache is made then) and empty at runtime, so a
     * value read with getenv() at runtime is always empty. Reading them
     * through config works because they are baked into the cache at build.
     */
    'host' => [
        'setup_token' => env('SETUP_TOKEN'),
        'tick_token' => env('TICK_TOKEN'),
        // Shared with the public website so the API believes the visitor address it forwards.
        'site_api_key' => env('SITE_API_KEY'),
        'site_url' => env('SITE_URL'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
