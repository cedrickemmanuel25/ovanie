<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Les domaines sur lesquels les cookies d'authentification Sanctum
    | doivent être considérés comme "stateful". Cela inclut ton frontend
    | local et tes URLs ngrok.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS',
        'localhost,127.0.0.1,localhost:8000,127.0.0.1:8000,margurite-hailstoned-nonarticulately.ngrok-free.dev'
    )),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Les guards à utiliser pour authentifier les utilisateurs via Sanctum.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | La durée de vie des tokens. Laisser null pour que les tokens ne
    | expirent jamais.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Préfixe utilisé pour les tokens, si besoin.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware utilisé pour gérer la session et CSRF.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
