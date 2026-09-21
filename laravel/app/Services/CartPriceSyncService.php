<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;

class CartPriceSyncService
{
    /**
     * Synchronise les lignes catalogue avec le prix public actuel et vérifie
     * qu'un prix négocié provient bien d'une négociation acceptée du client.
     */
    public function sync(Cart $cart): array
    {
        $cart->loadMissing('items.product', 'items.negotiation');

        $changed = false;
        $messages = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            $catalogPrice = (float) ($product->final_price ?? $product->price ?? 0);
            $isNegotiated = ($item->price_source ?? 'catalog') === 'negotiated';

            if ($isNegotiated && ! $this->hasValidNegotiation($item, (int) $cart->user_id)) {
                $item->forceFill([
                    'price' => $catalogPrice,
                    'price_source' => 'catalog',
                    'negotiation_id' => null,
                ])->save();

                $changed = true;
                $messages[] = "Le prix négocié de {$product->name} n'est plus valide. Le prix catalogue a été restauré.";
                continue;
            }

            if (! $isNegotiated && abs((float) $item->price - $catalogPrice) >= 0.01) {
                $item->forceFill([
                    'price' => $catalogPrice,
                    'price_source' => 'catalog',
                    'negotiation_id' => null,
                ])->save();

                $changed = true;
                $messages[] = "Le prix de {$product->name} a été actualisé.";
            }
        }

        return [
            'changed' => $changed,
            'messages' => $messages,
        ];
    }

    /**
     * Vérification transactionnelle juste avant création des OrderItem.
     * Retourne null si le prix est encore valide, sinon le message d'erreur.
     */
    public function validationError(CartItem $item, Product $lockedProduct, int $userId): ?string
    {
        if (($item->price_source ?? 'catalog') === 'negotiated') {
            $item->loadMissing('negotiation');

            if (! $this->hasValidNegotiation($item, $userId)) {
                return 'Le prix négocié de ' . $lockedProduct->name . ' n’est plus valide. Revenez au panier pour actualiser la ligne.';
            }

            return null;
        }

        $currentPublicPrice = (float) ($lockedProduct->final_price ?? $lockedProduct->price ?? 0);

        if (abs((float) $item->price - $currentPublicPrice) >= 0.01) {
            return 'Le prix de ' . $lockedProduct->name . ' a changé. Revenez au panier pour vérifier le nouveau montant avant de commander.';
        }

        return null;
    }

    private function hasValidNegotiation(CartItem $item, int $userId): bool
    {
        $negotiation = $item->negotiation;
        $originalProductId = (int) ($item->original_product_id ?: $item->product_id);

        return $negotiation !== null
            && $negotiation->status === 'accepted'
            && (int) $negotiation->buyer_id === $userId
            && (int) $negotiation->product_id === $originalProductId
            && abs((float) $negotiation->proposed_price - (float) $item->price) < 0.01;
    }
}
