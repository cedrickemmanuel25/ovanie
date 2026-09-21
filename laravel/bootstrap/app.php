<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        /*
        |--------------------------------------------------------------------------
        | Configuration session locale / Cloudflare
        |--------------------------------------------------------------------------
        |
        | Ce middleware doit être exécuté avant StartSession.
        | Il permet de conserver votre fonctionnement actuel pour les tests
        | locaux, LAN et Cloudflare.
        |
        */

        $middleware->prepend(
            \App\Http\Middleware\LocalTunnelSessionConfig::class
        );

        /*
        |--------------------------------------------------------------------------
        | Proxies
        |--------------------------------------------------------------------------
        */

        $middleware->trustProxies(at: '*');

        /*
        |--------------------------------------------------------------------------
        | API stateful
        |--------------------------------------------------------------------------
        */

        $middleware->statefulApi();

        /*
        |--------------------------------------------------------------------------
        | Exceptions CSRF
        |--------------------------------------------------------------------------
        |
        | Le webhook PayDunya est appelé serveur-à-serveur.
        | Sa sécurité / validation doit être effectuée dans le contrôleur
        | PayDunya et non par le token CSRF de Laravel.
        |
        */

        $middleware->validateCsrfTokens(except: [
            'paydunya/webhook',
            'paydunya/payout/callback',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Alias des middlewares OVANIE
        |--------------------------------------------------------------------------
        */

        $middleware->alias([
            'isAdmin'       => \App\Http\Middleware\IsAdmin::class,
            'auth'          => \App\Http\Middleware\Authenticate::class,
            'role'          => \App\Http\Middleware\RoleMiddleware::class,
            'staff'         => \App\Http\Middleware\StaffRoleMiddleware::class,
            'internal'      => \App\Http\Middleware\InternalUserMiddleware::class,
            'vendor'        => \App\Http\Middleware\VendorMiddleware::class,
            'has.shop'      => \App\Http\Middleware\EnsureUserHasShop::class,
            'shop.approved' => \App\Http\Middleware\EnsureVendorShopApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();

/*
|--------------------------------------------------------------------------
| Public Path OVANIE
|--------------------------------------------------------------------------
|
| LOCAL
| -----
|
| Exemple :
|
| C:\Users\yaoce\Downloads\ovanie\laravel
|
| Laravel doit utiliser :
|
| C:\Users\yaoce\Downloads\ovanie\laravel\public
|
|
| PRODUCTION CPANEL
| -----------------
|
| Projet Laravel :
|
| /home/rtxqkd67/laravel
|
| DocumentRoot :
|
| /home/rtxqkd67/public_html
|
|
| Fonctionnement :
|
| - si public_html existe à côté du projet Laravel, Laravel l'utilise ;
| - si public_html n'existe pas, Laravel conserve automatiquement
|   son dossier public/ normal.
|
| Cela permet d'utiliser exactement le même bootstrap/app.php
| en LOCAL et en PRODUCTION.
|
*/

/*
| Ce basculement ne doit s'appliquer qu'au déploiement de PRODUCTION
| (dossier projet nommé "laravel", sibling de public_html). Sans cette
| vérification, un second projet cPanel installé sous le même compte
| (ex : /home/xxx/test.ovanie.com) retombe sur le MÊME /home/xxx/public_html
| que la prod, et charge silencieusement ses assets/manifest Vite au lieu
| des siens.
*/
$cpanelPublicPath = dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR
    . 'public_html';

if (basename(dirname(__DIR__)) === 'laravel' && is_dir($cpanelPublicPath)) {
    $app->usePublicPath($cpanelPublicPath);
}

/*
|--------------------------------------------------------------------------
| Retour de l'application
|--------------------------------------------------------------------------
*/

return $app;