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

    // Athena AI language model providers (free tiers)
    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1/chat/completions'),
        // tried in order; Llama models left Groq's free tier in Aug 2026
        'models' => array_filter(array_map('trim', explode(',', env('GROQ_MODELS', 'openai/gpt-oss-120b,openai/gpt-oss-20b')))),
    ],

    'huggingface' => [
        'token' => env('HF_TOKEN'),
        'url' => env('HF_URL', 'https://router.huggingface.co/v1/chat/completions'),
        'model' => env('HF_MODEL', 'meta-llama/Llama-3.1-8B-Instruct'),
    ],

    // Facebook Messenger (Meta Graph API)
    'meta' => [
        'verify_token' => env('META_VERIFY_TOKEN'),
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
        'app_secret' => env('META_APP_SECRET'),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
    ],

];
