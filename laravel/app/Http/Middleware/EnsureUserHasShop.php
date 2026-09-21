<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasShop
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->shop) {
            return redirect()
                ->route('open-shop')
                ->with('error', 'Vous devez créer une boutique avant d’accéder à l’espace vendeur.');
        }

        return $next($request);
    }
}
