<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ovanie AI Configuration
    |--------------------------------------------------------------------------
    |
    | Architecture neutre : aucun fournisseur externe n'est connecté ici.
    | Le but est de préparer une interface adaptable pour brancher plus tard
    | OpenAI, Gemini, Mistral, Claude, un modèle local ou un moteur interne.
    |
    */

    'enabled' => env('OVANIE_AI_ENABLED', false),

    'provider' => env('OVANIE_AI_PROVIDER', 'internal'),

    'providers' => [
        'internal' => [
            'driver' => 'rule_based',
        ],
        'openai' => [
            'driver' => 'external',
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],
        'gemini' => [
            'driver' => 'external',
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-pro'),
        ],
        'local' => [
            'driver' => 'local',
            'endpoint' => env('LOCAL_AI_ENDPOINT'),
        ],
    ],

    'recommendations' => [
        'limit' => env('OVANIE_AI_RECOMMENDATION_LIMIT', 8),
        'same_category_weight' => 40,
        'same_shop_weight' => 20,
        'popular_weight' => 20,
        'rating_weight' => 20,
    ],

    'price_analysis' => [
        'anomaly_threshold_percent' => env('OVANIE_AI_PRICE_ANOMALY_THRESHOLD', 35),
        'min_products_for_comparison' => 3,
    ],

    'calculator' => [
        'default_waste_margin' => 10,
        'currency' => env('OVANIE_CURRENCY', 'FCFA'),
    ],
];
