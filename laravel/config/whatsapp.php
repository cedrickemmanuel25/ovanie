<?php

$publicNumber = preg_replace(
    '/\D+/',
    '',
    (string) env('OVANIE_SUPPORT_WHATSAPP_NUMBER', '2250161781818')
) ?: null;

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business OVANIE — Meta Cloud API
    |--------------------------------------------------------------------------
    |
    | Toutes les valeurs sensibles restent dans le fichier .env.
    | Le numéro public est le numéro WhatsApp Business intelligent que les
    | clients doivent ouvrir depuis le site. Il ne doit pas être codé en dur
    | dans les vues Blade.
    |
    */

    'provider' => env('SUPPORT_WHATSAPP_PROVIDER', 'none'),

    'enabled' => filter_var(
        env('SUPPORT_WHATSAPP_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),

    'public_number' => $publicNumber,

    'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'graph_base_url' => env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com'),
    'graph_version' => env('WHATSAPP_GRAPH_API_VERSION', 'v25.0'),
    'timeout' => (int) env('WHATSAPP_HTTP_TIMEOUT', 20),
    'allow_unsigned_testing' => filter_var(
        env('WHATSAPP_ALLOW_UNSIGNED_TESTING', false),
        FILTER_VALIDATE_BOOL,
    ),

    'app_id' => env('META_APP_ID'),
    'app_secret' => env('META_APP_SECRET'),
];
