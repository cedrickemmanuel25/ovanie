<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VendorMobileHasShop
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Authentification vendeur requise.',
            ], 401);
        }

        if (! $user->shop) {
            return response()->json([
                'message' => 'Créez votre boutique OVANIE avant d’utiliser cet espace vendeur.',
                'requires_shop_onboarding' => true,
            ], 403);
        }

        return $next($request);
    }
}
