<?php

/**
 * OVANIE — Registre central des référentiels métier Laravel.
 *
 * Étape 3 du socle commun Web ↔ Mobile.
 *
 * Ce fichier ne remplace pas le contrat officiel de l'étape 2 : il indique
 * comment Laravel expose et résout les référentiels définis dans
 * config/ovanie_contract.php, ainsi que quelques listes de compatibilité
 * déjà utilisées par l'application mais pas encore normalisées dans le
 * contrat canonique.
 */
return [
    'registry_version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | API commune de référentiels — Étape 4
    |--------------------------------------------------------------------------
    |
    | Lecture seule, publique et limitée par throttle. La compatibilité des
    | versions d'APK sera ajoutée à l'étape 5 ; ici nous exposons uniquement
    | la version du contrat et du registre afin de rendre la source observable.
    */
    'api' => [
        'endpoint_version' => 'v1',
        'route' => 'mobile/v1/reference-data',
        'meta_route' => 'mobile/v1/reference-data/meta',
    ],

    /*
    |--------------------------------------------------------------------------
    | Alias stables -> enums du contrat officiel
    |--------------------------------------------------------------------------
    */
    'contract_references' => [
        'product_states' => 'product_state',
        'commercial_sale_types' => 'commercial_sale_type',
        'selling_modes' => 'selling_mode',
        'product_units' => 'product_unit',
        'product_types' => 'product_type',
        'product_availability_statuses' => 'product_availability_status',
        'product_publication_statuses' => 'product_publication_status',
        'seller_types' => 'seller_type',
        'legal_forms' => 'legal_form',
        'identity_types' => 'identity_type',
        'logistics_types' => 'shop_logistics_type',
        'delivery_zones' => 'shop_delivery_zone',
        'vendor_payment_modes' => 'vendor_payment_mode',
        'mobile_money_operators' => 'mobile_money_operator',
        'client_genders' => 'client_gender',
        'client_account_types' => 'client_account_type',
        'address_types' => 'address_type',
        'driver_onboarding_statuses' => 'driver_onboarding_status',
        'driver_availability_statuses' => 'driver_availability_status',
        'vehicle_types' => 'vehicle_type',
        'week_days' => 'week_day',
        'vendor_preparation_statuses' => 'vendor_preparation_status',
        'delivery_providers' => 'delivery_provider',
        'delivery_statuses' => 'delivery_status',
        'mission_statuses' => 'mission_status',
        'return_statuses' => 'return_status',
        'return_logistics_statuses' => 'return_logistics_status',
        'payment_statuses' => 'payment_status',
        'payment_methods' => 'payment_method',
    ],

    /*
    |--------------------------------------------------------------------------
    | Référentiels dynamiques réellement stockés en base
    |--------------------------------------------------------------------------
    |
    | Ces noms sont documentaires. La résolution est effectuée par
    | OvanieReferenceDataService afin de conserver les scopes métier des
    | modèles (actif, ordre, hiérarchie, etc.).
    */
    'dynamic_references' => [
        'categories',
        'shop_categories',
        'product_categories',
        'communes',
        'quarters',
        'regions',
        'cities',
        'delivery_territory',
    ],

    /*
    |--------------------------------------------------------------------------
    | Compatibilité existante
    |--------------------------------------------------------------------------
    |
    | Ces listes existaient déjà dans les contrôleurs avant l'étape 3. Elles
    | sont regroupées ici pour supprimer les définitions parallèles sans
    | modifier le comportement des écrans. Elles pourront être promues dans
    | le contrat canonique lors d'une évolution explicitement validée.
    */
    'compatibility' => [
        'identity_countries' => [
            'ci' => "Côte d'Ivoire",
        ],
        'processing_times' => [
            'lt24h',
            '24_48h',
            '3_5j',
            '7j_plus',
        ],
        'payout_methods' => [
            'orange' => 'Orange Money',
            'mtn' => 'MTN MoMo',
            'moov' => 'Moov Money',
            'wave' => 'Wave',
            'bank' => 'Virement bancaire',
        ],
        'checkout_operator_order' => [
            'wave',
            'orange',
            'mtn',
            'moov',
            'card',
        ],
        'extra_labels' => [
            'card' => 'Carte bancaire',
            'bank' => 'Virement bancaire',
        ],
    ],
];
