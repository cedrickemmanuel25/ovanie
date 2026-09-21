<?php

/**
 * OVANIE — Compatibilité du contrat API avec les applications mobiles.
 *
 * Étape 5 du socle commun Web ↔ Mobile.
 *
 * Les APK annoncent le schéma qu'elles savent interpréter. Laravel compare
 * cette version au contrat courant avant que l'application poursuive son
 * démarrage. Une indisponibilité réseau ne bloque pas le démarrage ; seule une
 * incompatibilité explicitement confirmée par Laravel est bloquante.
 */
return [
    'compatibility_version' => '1.0.0',

    'route' => 'mobile/v1/reference-data/compatibility',

    'apps' => [
        'client' => [
            'label' => 'OVANIE Client',
            'supported_schema_min' => '1.0.0',
            'supported_schema_max' => '1.0.0',
        ],
        'vendor' => [
            'label' => 'OVANIE Vendeur',
            'supported_schema_min' => '1.0.0',
            'supported_schema_max' => '1.0.0',
        ],
        'driver' => [
            'label' => 'OVANIE Livreur',
            'supported_schema_min' => '1.0.0',
            'supported_schema_max' => '1.0.0',
        ],
        'commercial' => [
            'label' => 'OVANIE Commercial',
            'supported_schema_min' => '1.0.0',
            'supported_schema_max' => '1.0.0',
        ],
    ],

    'headers' => [
        'app' => 'X-Ovanie-App',
        'schema' => 'X-Ovanie-Schema-Version',
    ],
];
