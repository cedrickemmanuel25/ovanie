<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

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

        // OVANIE Pro possède une session indépendante de la plateforme OVANIE.
        // Le middleware garde un cookie dédié au sous-domaine Pro et doit
        // s'exécuter avant StartSession.
        $middleware->prepend(
            \App\Http\Middleware\OvanieSharedSessionConfig::class
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
            // Compteur de limitation par route (voir ThrottleRequestsPerRoute).
            'throttle'      => \App\Http\Middleware\ThrottleRequestsPerRoute::class,
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
        /*
        |--------------------------------------------------------------------------
        | Expiration de session / CSRF
        |--------------------------------------------------------------------------
        |
        | Une session expirée ne doit jamais afficher l'écran technique Laravel
        | "419 PAGE EXPIRED" au personnel OVANIE. Pour les espaces internes,
        | on revient proprement au portail de connexion avec une explication.
        | Les autres écrans 419 utilisent la vue professionnelle errors.419.
        |
        */
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            $request = request();
            $isInternalArea = $request->is('administration/*')
                || $request->is('admin/*')
                || $request->is('logistique/*')
                || $request->is('support/*')
                || $request->is('commercial/*');

            if ($isInternalArea) {
                return redirect()->route('admin.adminlogin')->with(
                    'warning',
                    'Votre session sécurisée a été renouvelée. Veuillez vous reconnecter.'
                );
            }

            // Espaces publics (clients, vendeurs, livreurs) : jamais de page
            // d'erreur technique quand le jeton d'un formulaire resté ouvert a
            // expiré. On traite chaque cas comme l'utilisateur l'attend.
            if ($request->expectsJson()) {
                return $response;
            }

            // Déconnexion : l'intention est claire, on déconnecte.
            if ($request->is('logout') || $request->routeIs('logout', '*.logout')) {
                foreach (['web', 'driver', 'pro'] as $guard) {
                    try {
                        auth($guard)->logout();
                    } catch (\Throwable) {
                    }
                }
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/');
            }

            // Déjà connecté (autre onglet, cookie « rester connecté ») :
            // inutile de renvoyer vers le formulaire de connexion.
            if (auth('web')->check() && $request->routeIs('login.web', 'register.post')) {
                $user = auth('web')->user();

                return redirect()->route(
                    ($user->role ?? null) === 'vendor' ? 'vendor.dashboard' : 'client.dashboard'
                );
            }

            // Sinon : retour au formulaire avec la saisie conservée (hors mots
            // de passe) et un message neutre ; la page porte un jeton neuf.
            return redirect()->back(fallback: '/')
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('info', 'Pour votre sécurité, la page a été actualisée. Merci de valider à nouveau.');
        });
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