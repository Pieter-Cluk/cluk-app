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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Staff login with Microsoft 365 (Entra ID app registration "CLUK app",
    // single tenant: only @cluk.live accounts).
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', '/auth/microsoft/callback'),
        'tenant' => env('MICROSOFT_TENANT_ID', 'organizations'),
    ],

    // Translating messages (Media › Messages) with Claude.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-5-5'),
    ],

    // Website forms post requests to /intake with this token (header X-Intake-Token).
    'intake' => [
        'token' => env('INTAKE_TOKEN'),
    ],

    // KBO/BCE Public Search web service (free, 3000 lookups a day): live register data for the credit check.
    'kbo_ws' => [
        'url' => env('KBO_WS_URL', 'https://kbopub.economie.fgov.be/kbopubws180000/services/wsKBOPub'),
        'user' => env('KBO_WS_USER'),
        'password' => env('KBO_WS_PASSWORD'),
        'stale_days' => 7,      // a company's register data older than this is looked up again
        'per_run' => 5,         // background lookups every 10 minutes (max 720 a day, well under the 3000)
    ],

];
