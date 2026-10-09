<?php

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => 'users',
    ],

    'guards' => [
        /*
         * Clients et vendeurs.
         */
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
            // Connexion mémorisée (cookie « remember ») : le client ou le
            // vendeur reste connecté 1 an tant qu'il ne se déconnecte pas.
            'remember' => (int) env('AUTH_WEB_REMEMBER_MINUTES', 525600),
        ],

        /*
         * OVANIE Pro : comptes complètement séparés des comptes publics OVANIE.
         */
        'pro' => [
            'driver' => 'session',
            'provider' => 'pro_users',
        ],

        /*
         * Personnel interne OVANIE : administration, logistique, support et commercial.
         * Tous ces profils utilisent le même guard afin d'éviter les sessions dupliquées.
         */
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],

        /*
         * Livreurs terrain. Leur portail reste volontairement séparé des comptes internes.
         */
        'driver' => [
            'driver' => 'session',
            'provider' => 'delivery_drivers',
        ],

        'api' => [
            'driver' => 'token',
            'provider' => 'users',
            'hash' => false,
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\PublicUser::class,
        ],

        /*
         * Le provider interne utilise un modèle filtré dédié : seuls Administration,
         * Logistique, Support et Commercial peuvent être chargés par ce guard.
         */
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\InternalUser::class,
        ],

        'delivery_drivers' => [
            'driver' => 'eloquent',
            'model' => App\Models\DeliveryDriver::class,
        ],

        'pro_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\ProUser::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'pro_users' => [
            'provider' => 'pro_users',
            'table' => 'pro_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'admins' => [
            'provider' => 'admins',
            'table' => 'admin_password_resets',
            'expire' => 60,
            'throttle' => 60,
        ],

        'delivery_drivers' => [
            'provider' => 'delivery_drivers',
            'table' => 'password_reset_tokens',
            'expire' => 30,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
