<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Suggestion IA de catégorie produit
    |--------------------------------------------------------------------------
    |
    | Désactivée par défaut, comme l'amélioration IA des photos produit (voir
    | config/product-images.php). Quand elle est activée (et qu'une clé OpenAI
    | est configurée dans services.openai.key), le nom saisi par le vendeur ou
    | le commercial est envoyé à l'IA qui doit obligatoirement choisir une
    | catégorie/sous-catégorie parmi celles qui existent réellement - jamais
    | une catégorie inventée. Le vendeur/commercial garde toujours la main
    | pour corriger la suggestion si elle est incorrecte.
    |
    */
    'ai_suggestion_enabled' => env('AI_PRODUCT_CATEGORY_ENABLED', false),
];
