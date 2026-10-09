<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorShopConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        $shop = $request->user()?->shop;

        if (! $shop || $shop->configurationReady()) {
            return $next($request);
        }

        $routeName = (string) optional($request->route())->getName();

        if (str_starts_with($routeName, 'vendor.configuration.')) {
            return $next($request);
        }

        // Tant que la partie logistique n'est pas terminée, le vendeur doit
        // pouvoir ouvrir les écrans de configuration de sa propre logistique.
        if ($shop->needsLogisticsConfiguration() && str_starts_with($routeName, 'vendor.delivery.')) {
            return $next($request);
        }

        return redirect()
            ->route('vendor.configuration.index')
            ->with('warning', 'Finalisez la configuration de votre boutique avant de poursuivre. Vos produits restent enregistrés, mais ils ne peuvent pas être validés au checkout tant que cette étape n’est pas terminée.');
    }
}
