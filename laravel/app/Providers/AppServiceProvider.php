<?php

namespace App\Providers;

use App\Models\Order;
use App\Listeners\SendMobilePushForDatabaseNotification;
use App\Models\OrderItem;
use App\Models\Devis;
use App\Models\AppelOffre;
use App\Models\Dispute;
use App\Models\ProductImage;
use App\Policies\AppelOffrePolicy;
use App\Policies\DevisPolicy;
use App\Policies\DisputePolicy;
use App\Policies\ProductImagePolicy;
use App\Observers\OrderItemObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductImageObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Devis::class, DevisPolicy::class);
        Gate::policy(AppelOffre::class, AppelOffrePolicy::class);
        Gate::policy(Dispute::class, DisputePolicy::class);
        Gate::policy(ProductImage::class, ProductImagePolicy::class);

        // En développement local (php artisan serve sur 127.0.0.1/localhost),
        // ne jamais forcer https : le serveur local ne parle pas TLS, donc
        // tout asset() généré en https (JS, CSS, images) échoue en silence
        // et casse des pages entières (ex. catalogue qui reste bloqué sur
        // "Chargement des produits…" car catalog.js ne charge jamais). Cette
        // situation se produit dès que APP_ENV=production en local (copie
        // d'un .env de prod). On ne désactive ce garde-fou que pour une
        // vraie requête HTTP locale : jamais en console/queue (les liens
        // https générés dans les emails/notifications de production restent
        // corrects).
        if (self::shouldForceHttpsScheme(
            runningInConsole: $this->app->runningInConsole(),
            requestHost: $this->app->runningInConsole() ? '' : request()->getHost(),
            appUrl: (string) config('app.url'),
            isProductionEnv: $this->app->environment('production'),
        )) {
            URL::forceScheme('https');
        }

        // Les routes sont enregistrées uniquement hors cache ; lors de la génération
        // du cache elles sont incluses, puis Laravel recharge uniquement le cache.
        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(base_path('routes/open-shop.php'));
        }

        // Synchronisation immédiate du calendrier de reversement lorsqu'une livraison,
        // une réception client ou un paiement change d'état.
        OrderItem::observe(OrderItemObserver::class);
        Order::observe(OrderObserver::class);

        // Régénère automatiquement une photo produit fraîchement uploadée en
        // version "catalogue pro" via l'IA (désactivé par défaut, voir
        // config/product-images.php ai_enhancement_enabled).
        ProductImage::observe(ProductImageObserver::class);

        // Toute notification Laravel enregistrée en base (ex. logistique) peut
        // aussi devenir un vrai push FCM sans créer un flux mobile parallèle.
        Event::listen(NotificationSent::class, SendMobilePushForDatabaseNotification::class);

        View::composer(['layouts._navbar', 'layouts.guest', 'layouts.app', '*'], function ($view) {
            $cartCount = 0;
            $favoriteCount = 0;

            try {
                if (auth()->guard('web')->check()) {
                    if (Schema::hasTable('carts') && Schema::hasTable('cart_items')) {
                        $cart = auth()->guard('web')->user()->cart;
                        $cartCount = $cart ? (int) $cart->items()->sum('quantity') : 0;
                    }

                    if (method_exists(auth()->guard('web')->user(), 'favoriteProducts')) {
                        $favoriteCount = (int) auth()->guard('web')->user()->favoriteProducts()->count();
                    }
                } elseif (request()->hasSession()) {
                    $cartCount = app(\App\Services\GuestCartService::class)->count(request());
                }
            } catch (\Throwable) {
                $cartCount = 0;
                $favoriteCount = 0;
            }

            $view->with(compact('cartCount', 'favoriteCount'));
        });
    }

    /**
     * Décide si les URL générées (asset(), url(), route()...) doivent être
     * forcées en https. Jamais pour une vraie requête HTTP locale
     * (127.0.0.1/localhost, ex. php artisan serve) même si APP_ENV=production
     * par erreur : un serveur de développement ne parle pas TLS, donc forcer
     * https y casse silencieusement tous les assets (voir le commentaire
     * dans boot()). Toujours forcé en console/queue et pour toute requête
     * réelle sur un autre host (production, ngrok).
     */
    public static function shouldForceHttpsScheme(
        bool $runningInConsole,
        string $requestHost,
        string $appUrl,
        bool $isProductionEnv,
    ): bool {
        $isLocalDevRequest = ! $runningInConsole
            && in_array($requestHost, ['127.0.0.1', 'localhost'], true);

        if ($isLocalDevRequest) {
            return false;
        }

        return str_contains($appUrl, 'ngrok-free.dev') || $isProductionEnv;
    }
}
