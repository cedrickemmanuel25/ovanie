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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS SERVICES (Côte d'Ivoire)
    |--------------------------------------------------------------------------
    */

    'orange_sms' => [
        'client_id' => env('ORANGE_SMS_CLIENT_ID'),
        'client_secret' => env('ORANGE_SMS_CLIENT_SECRET'),
        'sender' => env('ORANGE_SMS_SENDER'),
        'url' => env('ORANGE_SMS_URL'),
    ],

    'mtn_sms' => [
        'client_id' => env('MTN_SMS_CLIENT_ID'),
        'client_secret' => env('MTN_SMS_CLIENT_SECRET'),
        'sender' => env('MTN_SMS_SENDER'),
        'url' => env('MTN_SMS_URL'),
    ],

    'wave' => [
        'url' => env('WAVE_API_URL'),
        'secret' => env('WAVE_SECRET_KEY'),
        'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
    ],

    'business' => [
        'contact_access_price' => (int) env('BUSINESS_CONTACT_ACCESS_PRICE', 5000),
    ],

    'africastalking' => [
        'username' => env('AFRICASTALKING_USERNAME'),
        'key' => env('AFRICASTALKING_API_KEY'),
        'sender' => env('AFRICASTALKING_SENDER_ID', 'OVANIE'),
    ],
    /*
    |--------------------------------------------------------------------------
    | SOCIAL AUTHENTICATION (Socialite)
    |--------------------------------------------------------------------------
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LOGISTICS MAPPING SERVICES
    |--------------------------------------------------------------------------
    */
    'mapbox' => [
        'public_token' => env('MAPBOX_PUBLIC_TOKEN'),
        'geocoding_token' => env('MAPBOX_GEOCODING_TOKEN'),
        'style_url' => env('MAPBOX_STYLE_URL', 'mapbox://styles/mapbox/streets-v12'),
        'language' => env('MAPBOX_LANGUAGE', 'fr'),
        'search_country' => env('MAPBOX_SEARCH_COUNTRY', 'CI'),
    ],
    'tomtom' => [
        'api_key' => env('TOMTOM_API_KEY'),
        'key' => env('TOMTOM_API_KEY'),
        'routing_base_url' => env('TOMTOM_ROUTING_BASE_URL', 'https://api.tomtom.com/routing/1'),
        'traffic' => filter_var(env('TOMTOM_TRAFFIC', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
