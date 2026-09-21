<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reclassement global des anciens produits OVANIE
    |--------------------------------------------------------------------------
    | La commande globale ne déplace jamais un produit sur la simple base de sa
    | catégorie actuelle. Elle compare le contenu du produit à TOUTES les
    | sous-catégories actives puis déduit automatiquement le parent de la cible.
    |
    | Une proposition n'est appliquée que si le score ET l'écart avec la
    | deuxième proposition sont suffisants.
    */
    'minimum_score' => 110,
    'minimum_margin' => 35,

    // Petit bonus seulement : l'ancienne catégorie peut être correcte, mais on
    // sait qu'une partie des anciens produits a été rangée dans un mauvais parent.
    'current_parent_bonus' => 12,

    'field_scores' => [
        'name' => 120,
        'type' => 95,
        'short_description' => 60,
        'usage_area' => 55,
        'description' => 32,
        'technical_details' => 28,
        'attributes' => 28,
        'brand' => 12,
        'packaging' => 12,
    ],

    /*
     * Expressions particulièrement fiables. Lorsqu'elles apparaissent dans le
     * nom/type du produit, elles donnent un signal fort à UNE sous-catégorie.
     * Elles servent surtout à corriger les catégories principales historiquement
     * erronées (carrelage rangé dans Outillage, solaire rangé dans Finition...).
     */
    'exclusive_keywords' => [
        'carrelage' => [
            'carrelage', 'carreaux', 'carreau', 'gres cerame',
        ],
        'wc-sanitaires' => [
            'wc', 'wc monobloc', 'wc chasse basse', 'cuvette wc', 'toilette',
            'reservoir separe', 'réservoir séparé',
        ],
        'eviers-lavabos' => [
            'evier', 'évier', 'evier de cuisine', 'évier de cuisine',
            'bac vaisselle', 'bac-vaisselle',
        ],
        'projecteurs-solaires' => [
            'projecteur solaire',
        ],
        'lampadaires-solaires' => [
            'lampadaire solaire',
        ],
        'panneaux-solaires' => [
            'panneau solaire', 'module solaire', 'panneau photovoltaique',
        ],
        'fer-a-beton' => [
            'fer a beton', 'fer à béton', 'fil d attache', "fil d'attache", 'fer d attache', "fer d'attache",
        ],
        'quincaillerie' => [
            'carton de pointe', 'cartons de pointe', 'pointe', 'pointes',
            'rivet', 'rivets', 'rivetage', 'rivation', 'boite de rivet', 'boîte de rivet',
        ],
        'gaines-conduits' => [
            'tube orange', 'tube orange plasticable', 'gaine orange', 'tube annele', 'tube annelé',
        ],
        'accessoires-electriques' => [
            'commutateur de transfert', 'commutateur de transfert automatique',
            'inverseur de source',
        ],
        'luminaires' => [
            'chandelier', 'spot led', 'spot 30w', 'projecteur a courant', 'projecteur à courant',
            'ampoule', 'lampe tempete', 'lampe tempête',
        ],
        'revetements-muraux' => [
            'lambris pvc', 'lambris mural',
        ],
        'outillage-a-main' => [
            'valise d outils', "valise d'outils", 'caisse a outils', 'caisse à outils',
        ],
        'agglos' => ['agglo', 'agglos', 'agglos creux', 'agglos plein'],
        'gravier' => ['gravier'],
        'sable' => ['sable'],
    ],

    /*
     * Compléments à catalog_subcategory_rules.php. Ils ne remplacent pas le
     * référentiel existant : ils l'enrichissent pour les anciens libellés vus en
     * production.
     */
    'extra_keywords' => [
        'fer-a-beton' => ['fil attache', 'fil d attache', 'fer attache'],
        'quincaillerie' => ['pointe acier', 'pointe beton', 'boite rivet', 'rivation'],
        'revetements-muraux' => ['lambris pvc', 'lambris'],
        'gaines-conduits' => ['tube orange', 'tube plasticable', 'gaine orange'],
        'accessoires-electriques' => ['commutateur transfert', 'inverseur source'],
        'carrelage' => ['carreaux', 'carreau'],
        'eviers-lavabos' => ['bac vaisselle', 'evier cuisine'],
        'wc-sanitaires' => ['wc monobloc', 'wc chasse basse', 'cuvette wc'],
        'luminaires' => ['chandelier', 'spot', 'projecteur a courant', 'ampoule'],
        'outillage-a-main' => ['valise outils', 'caisse outils'],
    ],

    // Les sous-catégories Reconditionnés ne sont candidates que si le produit
    // est déjà dans Nos reconditionnés ou si son état produit indique réellement
    // qu'il est reconditionné.
    'reconditioned_parent_slugs' => ['nos-reconditionnee', 'nos-reconditionnes'],
    'reconditioned_states' => ['reconditioned', 'reconditionne', 'reconditionnee', 'reconditionné', 'reconditionnée'],
];
