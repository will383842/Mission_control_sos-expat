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
        'failures_webhook' => env('SLACK_FAILURES_WEBHOOK'),
    ],

    'telegram_alerts' => [
        'bot_token' => env('TELEGRAM_ALERT_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_ALERT_CHAT_ID'),
    ],

    // Dedicated bot for LinkedIn interactions (confirm, comment replies)
    // Falls back to telegram_alerts if not set
    'telegram_linkedin' => [
        'bot_token' => env('TELEGRAM_LINKEDIN_BOT_TOKEN', env('TELEGRAM_ALERT_BOT_TOKEN')),
        'chat_id'   => env('TELEGRAM_LINKEDIN_CHAT_ID',   env('TELEGRAM_ALERT_CHAT_ID')),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
    ],

    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'model'   => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
        'timeout' => (int) env('CLAUDE_TIMEOUT', 180),
    ],

    'perplexity' => [
        'api_key' => env('PERPLEXITY_API_KEY', ''),
        'model'   => env('PERPLEXITY_MODEL', 'sonar'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'translation_model' => env('OPENAI_TRANSLATION_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 180),
    ],

    'dalle' => [
        'model' => env('DALLE_MODEL', 'dall-e-3'),
        'timeout' => (int) env('DALLE_TIMEOUT', 180),
    ],

    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY', ''),
    ],

    'linkedin' => [
        'client_id'     => env('LINKEDIN_CLIENT_ID', ''),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET', ''),
        'redirect_uri'  => env('LINKEDIN_REDIRECT_URI', ''),
        // URL where user lands after OAuth (your Mission Control dashboard)
        'dashboard_url' => env('LINKEDIN_DASHBOARD_URL', '/'),
        // Telegram 1-tap confirm mode (ToS-compliant interim while Community Management API pending)
        // Set to true to require manual tap on Telegram before each LinkedIn post goes live
        'telegram_confirm'          => (bool) env('LINKEDIN_TELEGRAM_CONFIRM', false),
        'telegram_webhook_secret'   => env('TELEGRAM_LINKEDIN_WEBHOOK_SECRET', ''),
    ],

    'ai' => [
        'daily_budget' => (int) env('AI_DAILY_BUDGET', 5000),
        'monthly_budget' => (int) env('AI_MONTHLY_BUDGET', 100000),
        'alert_email' => env('AI_ALERT_EMAIL', ''),
        'block_on_exceeded' => (bool) env('AI_BLOCK_ON_EXCEEDED', false),
    ],

    'indexnow' => [
        'enabled' => (bool) env('INDEXNOW_ENABLED', false),
        'key' => env('INDEXNOW_KEY', ''),
        'delay' => (int) env('INDEXNOW_DELAY', 60),
    ],

    'site' => [
        'url' => env('SITE_URL', 'https://sos-expat.com'),
        'name' => env('SITE_NAME', 'SOS-Expat'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID', ''),
        'service_account_key' => env('FIREBASE_SERVICE_ACCOUNT_KEY', ''),
    ],

    'blog' => [
        'url' => env('BLOG_API_URL', ''),
        'api_key' => env('BLOG_API_KEY', ''),
        'site_url' => env('BLOG_SITE_URL', 'https://sos-expat.com'),
    ],

    'backlink_engine' => [
        'webhook_url' => env('BACKLINK_ENGINE_WEBHOOK_URL', ''),
        'webhook_secret' => env('BACKLINK_ENGINE_WEBHOOK_SECRET', ''),
    ],

    // Machine-to-machine token for automated scripts (Q/R generator, etc.)
    'machine_api_token' => env('MACHINE_API_TOKEN', ''),

];
