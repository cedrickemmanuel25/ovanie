<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureVendorShopApproved
{
    /**
     * Bloque les actions de vente tant que la boutique n'est pas approuvée.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Non authentifié.'], 401)
                : redirect()->route('login');
        }

        $shop = $user->shop;

        if (! $shop) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Vous devez créer une boutique.'], 403)
                : redirect()
                    ->route('open-shop')
                    ->with('error', 'Vous devez créer une boutique avant d’accéder à l’espace vendeur.');
        }

        $isApproved = $shop->status === 'approved' && (bool) $shop->is_active === true;

        if (! $isApproved) {
            $message = match ($shop->status) {
                'pending' => 'Votre boutique est en attente de validation. Vous pourrez vendre après approbation par OVANIE.',
                'rejected' => 'Votre boutique a été rejetée. Consultez la raison du rejet et corrigez votre dossier.',
                default => 'Votre boutique n’est pas encore autorisée à vendre.',
            };

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'shop_status' => $shop->status,
                    'is_active' => (bool) $shop->is_active,
                    'rejection_reason' => $shop->rejection_reason,
                ], 403);
            }

            return redirect()->route('vendor.shop-status')->with('warning', $message);
        }

        return $next($request);
    }
}
