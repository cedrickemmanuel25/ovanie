<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VendorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (($user->role ?? null) !== 'vendor') {
            return redirect()
                ->route('client.dashboard')
                ->with('warning', 'Cet espace est réservé aux identifiants vendeurs.');
        }

        $hasShop = method_exists($user, 'shop')
            ? $user->shop()->exists()
            : false;

        if ($hasShop) {
            return $next($request);
        }

        return redirect()
            ->route('open-shop')
            ->with('warning', 'Finalisez l’ouverture de votre boutique pour accéder à l’espace vendeur.');
    }
}
