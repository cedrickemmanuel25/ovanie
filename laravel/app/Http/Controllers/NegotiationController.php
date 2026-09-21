<?php

namespace App\Http\Controllers;

use App\Models\Negotiation;
use App\Models\Product;
use Illuminate\Http\Request;

class NegotiationController extends Controller
{
    /**
     * Vérifie une proposition client sans afficher les seuils vendeur.
     *
     * Logique :
     * - Le vendeur définit en interne 3 seuils : price_p1, price_p2, price_p3.
     * - Le client ne voit jamais ces seuils.
     * - Si la proposition est au moins égale au dernier seuil accepté par le vendeur,
     *   elle est acceptée et le client peut ajouter au panier au prix proposé.
     * - Si la proposition est trop basse, elle est refusée sans révéler le minimum.
     */
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'proposed_price' => ['required', 'numeric', 'min:1'],
        ]);

        if (! $product->is_negotiable || ! $product->price_p3) {
            return response()->json([
                'success' => false,
                'accepted' => false,
                'message' => 'Ce produit n’est pas négociable.',
            ], 403);
        }

        $proposedPrice = (float) $validated['proposed_price'];
        $displayPrice = (float) ($product->final_price ?? $product->promo_price ?? $product->price ?? 0);

        $sellerOffer1 = (float) ($product->price_p1 ?: $displayPrice);
        $sellerOffer2 = (float) ($product->price_p2 ?: $sellerOffer1);
        $sellerOffer3 = (float) ($product->price_p3 ?: $sellerOffer2);

        $minimumAccepted = min($sellerOffer1, $sellerOffer2, $sellerOffer3);
        $maximumAccepted = max($displayPrice, $sellerOffer1, $sellerOffer2, $sellerOffer3);

        if ($displayPrice > 0 && $proposedPrice > $maximumAccepted) {
            return response()->json([
                'success' => false,
                'accepted' => false,
                'message' => 'Votre proposition ne doit pas dépasser le prix affiché.',
            ], 422);
        }

        $status = $proposedPrice >= $minimumAccepted ? 'accepted' : 'rejected';

        $negotiation = Negotiation::create([
            'product_id' => $product->id,
            'shop_id' => $product->shop_id,
            'buyer_id' => auth()->id(),
            'proposed_price' => $proposedPrice,
            'status' => $status,
        ]);

        if ($status === 'accepted') {
            return response()->json([
                'success' => true,
                'accepted' => true,
                'negotiation_id' => $negotiation->id,
                'accepted_price' => $proposedPrice,
                'message' => 'Votre proposition est acceptée. Vous pouvez ajouter le produit au panier.',
            ]);
        }

        return response()->json([
            'success' => false,
            'accepted' => false,
            'negotiation_id' => $negotiation->id,
            'message' => 'Votre proposition est trop basse. Essayez un montant plus proche du prix affiché.',
        ], 422);
    }
}
