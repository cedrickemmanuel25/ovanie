<?php

use App\Support\SanctumStatefulDomains;
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
    | Les hôtes de développement local (127.0.0.1/localhost) sont toujours
    | ajoutés, même si SANCTUM_STATEFUL_DOMAINS est défini dans .env sans
    | eux (ex. copie d'un .env de production qui ne liste que le domaine
    | réel). Sans ça, EnsureFrontendRequestsAreStateful::fromFrontend() ne
    | reconnaît jamais un `php artisan serve` local comme "frontend" : la
    | session ne charge jamais pour les requêtes fetch() du navigateur vers
    | /api/*, qui échouent alors en 401 "Non authentifié" sans indice côté
    | serveur - un utilisateur pourtant bien connecté ne peut plus parler à
    | aucune fonctionnalité passant par une route API (ex. l'assistant IA).
    | Rien de tout ça n'affecte la vraie production, jamais accédée via ces
    | noms d'hôte littéraux.
    |
    */

    'stateful' => SanctumStatefulDomains::merge(
        env('SANCTUM_STATEFUL_DOMAINS',
            'localhost,127.0.0.1,localhost:8000,127.0.0.1:8000,margurite-hailstoned-nonarticulately.ngrok-free.dev'
        ),
        ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'],
    ),

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
