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

    'resend'   => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses'      => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack'    => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google'   => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'linkedin' => [
        'client_id'     => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
    ],

    'ai'       => [
        'active_provider'       => env('AI_ACTIVE_PROVIDER', 'openai'),
        'temperature'           => env('AI_TEMPERATURE', 0.7),
        'max_tokens'            => env('AI_MAX_TOKENS', 1000),
        'rescore_delay_seconds' => env('AI_RESCORE_DELAY_SECONDS', 30),
        'verify_ssl'            => env('AI_HTTP_VERIFY_SSL', true),
        'request_retries'       => env('AI_HTTP_RETRIES', 6),
        'retry_delay_ms'        => env('AI_HTTP_RETRY_DELAY_MS', 1500),
        'providers'             => [
            'openai_enabled' => env('AI_OPENAI_ENABLED', true),
            'gemini_enabled' => env('AI_GEMINI_ENABLED', false),
        ],
    ],

    'openai'   => [
        'api_key' => env('OPENAI_API_KEY'),
        'model'   => env('OPENAI_MODEL', 'gpt-4.1-mini'),
    ],

    'gemini'   => [
        'api_key' => env('GEMINI_API_KEY'),
        'model'   => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    // Makes the documents' PDFs (Doc 14 section 14.13) - see docker-compose.yml.
    'gotenberg' => [
        'url'     => env('GOTENBERG_URL', 'http://gotenberg:3000'),
        'timeout' => (int) env('GOTENBERG_TIMEOUT', 60),
    ],

];
