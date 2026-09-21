<?php

$whatsappPublicNumber = preg_replace(
    '/\D+/',
    '',
    (string) env('OVANIE_SUPPORT_WHATSAPP_NUMBER', '2250161781818')
) ?: null;

return [
    /*
    |--------------------------------------------------------------------------
    | Canaux officiels
    |--------------------------------------------------------------------------
    |
    | WhatsApp et téléphone humain sont volontairement séparés.
    */
    'support_phone' => env('OVANIE_SUPPORT_PHONE', '01 61 78 18 18'),
    'whatsapp_public_number' => $whatsappPublicNumber,

    /* Uniquement après échec final de Miss Salomé. */
    'human_escalation_phone' => env('OVANIE_HUMAN_ESCALATION_PHONE', '01 61 78 18 18'),
    'local_rate_notice' => env('OVANIE_SUPPORT_LOCAL_RATE_NOTICE', 'appel facturé au tarif local'),

    'queue' => env('SUPPORT_QUEUE', 'default'),

    'ai' => [
        // Le pipeline conversationnel V3 utilise actuellement Anthropic.
        'provider' => env('SUPPORT_AI_PROVIDER', 'anthropic'),
        'endpoint' => env('SUPPORT_AI_ENDPOINT'),
        'api_key' => env('SUPPORT_AI_API_KEY'),
        'model' => env('SUPPORT_AI_MODEL', 'support-local-v1'),
        'timeout' => (int) env('SUPPORT_AI_TIMEOUT', 30),

        'anthropic' => [
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
            'max_output_tokens' => (int) env('ANTHROPIC_MAX_OUTPUT_TOKENS', 900),
            'max_tool_rounds' => (int) env('ANTHROPIC_MAX_TOOL_ROUNDS', 6), // conservé pour compatibilité, non utilisé par le nouveau pipeline
            'planner_max_output_tokens' => (int) env('ANTHROPIC_PLANNER_MAX_OUTPUT_TOKENS', 550),
        ],

        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'project_id' => env('OPENAI_PROJECT_ID'),
            'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
            'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 700),
            'store' => filter_var(env('OPENAI_STORE_RESPONSES', false), FILTER_VALIDATE_BOOL),
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Contexte conversationnel multi-clients
    |--------------------------------------------------------------------------
    */
    'conversation' => [
        'recent_messages' => (int) env('SUPPORT_AI_RECENT_MESSAGES', 12),
        'memory_ttl_minutes' => (int) env('SUPPORT_AI_MEMORY_TTL_MINUTES', 1440),
        'lock_ttl_seconds' => (int) env('SUPPORT_WHATSAPP_LOCK_TTL', 300),
        'lock_wait_seconds' => (int) env('SUPPORT_WHATSAPP_LOCK_WAIT', 20),
    ],

    'context' => [
        'entity_limit' => (int) env('SUPPORT_AI_ENTITY_LIMIT', 4),
        'knowledge_limit' => (int) env('SUPPORT_AI_KNOWLEDGE_LIMIT', 5),
    ],

    'telephony' => [
        'provider' => env('SUPPORT_TELEPHONY_PROVIDER', 'none'),
        'public_base_url' => env('SUPPORT_TELEPHONY_PUBLIC_BASE_URL', env('APP_URL')),
        'outbound_url' => env('SUPPORT_TELEPHONY_OUTBOUND_URL'),
        'api_token' => env('SUPPORT_TELEPHONY_API_TOKEN'),
        'webhook_secret' => env('SUPPORT_TELEPHONY_WEBHOOK_SECRET'),
        'from_number' => env('SUPPORT_TELEPHONY_FROM_NUMBER'),
        'human_transfer_number' => env(
            'SUPPORT_TELEPHONY_HUMAN_TRANSFER_NUMBER',
            env('OVANIE_HUMAN_ESCALATION_PHONE', '01 61 78 18 18')
        ),
        'timeout' => (int) env('SUPPORT_TELEPHONY_TIMEOUT', 20),
        'record_calls' => filter_var(env('SUPPORT_TELEPHONY_RECORD_CALLS', false), FILTER_VALIDATE_BOOL),
        'auto_transfer_sensitive' => false,
        'voice_language' => env('SUPPORT_TELEPHONY_VOICE_LANGUAGE', 'fr-FR'),
        'voice_name' => env('SUPPORT_TELEPHONY_VOICE_NAME', 'alice'),
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'allow_unsigned_testing' => filter_var(env('TWILIO_ALLOW_UNSIGNED_TESTING', false), FILTER_VALIDATE_BOOL),
        ],
    ],

    'integrations' => [
        'whatsapp' => [
            'provider' => env('SUPPORT_WHATSAPP_PROVIDER', 'none'),
            'enabled' => filter_var(env('SUPPORT_WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOL),
            'graph_base_url' => env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com'),
            'graph_version' => env('WHATSAPP_GRAPH_API_VERSION', 'v25.0'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'app_secret' => env('META_APP_SECRET'),
            'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
            'timeout' => (int) env('WHATSAPP_HTTP_TIMEOUT', 20),
            'allow_unsigned_testing' => filter_var(env('WHATSAPP_ALLOW_UNSIGNED_TESTING', false), FILTER_VALIDATE_BOOL),
        ],
    ],
];
