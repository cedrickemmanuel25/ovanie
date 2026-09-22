<?php

namespace App\Http\Controllers;

use App\Models\Negotiation;
use App\Models\Product;
use Illuminate\Http\Request;

class NegotiationController extends Controller
{
    /**
     * Durée pendant laquelle la dernière offre (la plus basse) reste valable
     * une fois affichée au client, avant que la négociation n'expire.
     */
    public const FINAL_OFFER_TTL_SECONDS = 120;

    /**
     * Donne au client connecté les 3 offres définies par le vendeur
     * (price_p1/p2/p3), du prix le plus proche du prix affiché au plus
     * intéressant, pour la négociation guidée en 3 paliers de la fiche
     * produit. Route protégée par le middleware "auth" : un visiteur non
     * connecté n'accède jamais à ces montants.
     */
    public function offers(Product $product)
    {
        if (! $product->is_negotiable || ! $product->price_p3) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit n’est pas négociable.',
            ], 403);
        }

        $displayPrice = (float) ($product->final_price ?? $product->promo_price ?? $product->price ?? 0);
        $offer1 = (float) ($product->price_p1 ?: $displayPrice);
        $offer2 = (float) ($product->price_p2 ?: $offer1);
        $offer3 = (float) ($product->price_p3 ?: $offer2);

        return response()->json([
            'success' => true,
            'offers' => [
                (int) round($offer1),
                (int) round($offer2),
                (int) round($offer3),
            ],
            'final_offer_ttl_seconds' => self::FINAL_OFFER_TTL_SECONDS,
        ]);
    }

    /**
     * Valide qu'un montant choisi par le client parmi les offres ci-dessus
     * correspond bien à un seuil réellement défini par le vendeur, et
     * enregistre la négociation. Ne fait jamais confiance à un montant
     * envoyé par le client sans le revérifier contre price_p1/p2/p3.
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
