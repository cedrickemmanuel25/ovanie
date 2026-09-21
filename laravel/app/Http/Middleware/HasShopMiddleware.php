<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HasShopMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $shop = method_exists($user, 'shop') ? $user->shop : null;

        if (! $shop) {
            return redirect()
                ->route('open-shop')
                ->with('warning', 'Créez votre boutique avant d’accéder à l’espace vendeur.');
        }

        // Le middleware vérifie l'existence de la boutique mais ne modifie aucun état métier.
        return $next($request);
    }
}
