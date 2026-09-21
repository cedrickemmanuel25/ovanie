<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Pour autoriser uniquement ton frontend, remplace '*' par la liste exacte
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
    // Exemple dans .env : CORS_ALLOWED_ORIGINS=http://localhost:3000,http://monapp.test

    // Les ports de Flutter Web changent à chaque lancement en développement.
    'allowed_origins_patterns' => array_filter(
        explode(',', env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
    ),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
