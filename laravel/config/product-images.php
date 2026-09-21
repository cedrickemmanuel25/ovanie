<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stockage
    |--------------------------------------------------------------------------
    */
    'disk' => env('PRODUCT_IMAGE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Variantes carrées générées
    |--------------------------------------------------------------------------
    */
    'sizes' => [
        'master' => 1200,
        'card' => 800,
        'thumb' => 400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Standard visuel marketplace OVANIE
    |--------------------------------------------------------------------------
    */
    'background' => [248, 250, 252], // #F8FAFC
    'padding_ratio' => 0.055,
    'webp_quality' => 88,

    /*
    |--------------------------------------------------------------------------
    | Qualité minimale de la source
    |--------------------------------------------------------------------------
    |
    | Une source extrêmement portrait ou paysage est refusée. C'est volontaire :
    | sans recadrage, elle resterait trop étroite dans une carte carrée ; avec
    | recadrage automatique, le produit risquerait d'être coupé.
    |
    */
    'minimum_width' => 800,
    'minimum_height' => 800,
    'minimum_ratio' => 0.75,      // portrait maximal : 3:4
    'maximum_ratio' => 1.333333,  // paysage maximal : 4:3

    'max_file_size_kb' => 5120,
    'max_source_pixels' => 40_000_000,

    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Amélioration IA des photos produit
    |--------------------------------------------------------------------------
    |
    | Désactivée par défaut. Quand elle est activée (et qu'une clé OpenAI est
    | configurée dans services.openai.key), chaque photo normalisée envoyée
    | par un vendeur ou un commercial déclenche en tâche de fond une
    | régénération de l'image en version "photo produit professionnelle".
    | La photo brute d'origine (original_path) est conservée en interne ;
    | seule la version générée devient visible sur le catalogue public.
    | En cas d'échec (clé absente, API indisponible, contenu refusé), la
    | photo normalisée classique reste affichée — jamais de produit sans
    | image à cause d'une erreur IA.
    |
    */
    'ai_enhancement_enabled' => env('AI_PRODUCT_IMAGES_ENABLED', false),
];
