<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Limites de la page d'accueil
    |--------------------------------------------------------------------------
    |
    | Les collections sont limitées directement en base de données. La vue ne
    | doit jamais charger des centaines de produits pour en afficher quelques-uns.
    |
    */
    'limits' => [
        'flash' => 12,
        'black_friday' => 8,
        'normal' => 12,
        'best_sellers' => 12,
        'latest' => 12,
        'featured' => 12,
        'categories' => 16,
        'banners' => 40,
        'home_ads' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombre de cartes visibles dans les trois panneaux principaux
    |--------------------------------------------------------------------------
    */
    'panel_display' => [
        'left' => 5,
        'middle' => 4,
        'right' => 5,
    ],

    /* Les collections publiques sont partagées entre visiteurs. Le panier et
       les favoris restent calculés séparément pour chaque utilisateur. */
    'cache' => [
        'public_ttl_seconds' => (int) env('HOMEPAGE_CACHE_TTL', 600),
        'version' => env('HOMEPAGE_CACHE_VERSION', 'v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance automatique
    |--------------------------------------------------------------------------
    */
    'maintenance' => [
        'free_boost_count' => 10,
        'free_boost_duration_days' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fenêtres promotionnelles par défaut
    |--------------------------------------------------------------------------
    */
    'promotion_windows' => [
        'flash_default_hours' => 24,
        'black_friday_default_days' => 7,
    ],


    /*
    |--------------------------------------------------------------------------
    | Règles de visibilité des promotions
    |--------------------------------------------------------------------------
    */
    'promotions' => [
        'timezone' => env('HOMEPAGE_PROMOTION_TIMEZONE', 'Africa/Abidjan'),
        'flash_default_hours' => 24,
        'black_friday_weekday' => 5,
    ],

    'app_links' => [
        'google_play' => env('OVANIE_GOOGLE_PLAY_URL'),
        'app_store' => env('OVANIE_APP_STORE_URL'),
    ],
];
