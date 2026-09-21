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

        if (str_contains((string) config('app.url'), 'ngrok-free.dev') || $this->app->environment('production')) {
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
}
