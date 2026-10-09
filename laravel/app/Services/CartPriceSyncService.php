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
        $cart->loadMissing('items.product', 'items.variant', 'items.negotiation');

        $changed = false;
        $messages = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            // Un produit ancien peut recevoir des variantes après avoir déjà été
            // ajouté au panier. Dans ce cas, l'ancienne ligne sans variante ne
            // doit jamais être commandée comme si l'image principale représentait
            // le modèle choisi. Le client devra sélectionner son modèle exact.
            $hasActiveVariants = $product->variants()->where('is_active', true)->exists();
            if ($hasActiveVariants && ! $item->product_variant_id) {
                $messages[] = "{$product->name} possède maintenant plusieurs modèles/couleurs. Sélectionnez le modèle souhaité sur la fiche produit avant de commander.";
                continue;
            }

            if ($item->variant) {
                $variantImage = $item->variant->card_url ?: $item->variant->image_url;
                if ($item->variant_label_snapshot !== $item->variant->label
                    || (string) $item->variant_image_snapshot !== (string) $variantImage) {
                    $item->forceFill([
                        'variant_label_snapshot' => $item->variant->label,
                        'variant_image_snapshot' => $variantImage ?: null,
                    ])->save();
                    $changed = true;
                }
            }

            $catalogPrice = (float) ($item->variant?->price_override ?? $product->final_price ?? $product->price ?? 0);
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
        $hasActiveVariants = $lockedProduct->variants()->where('is_active', true)->exists();
        if ($hasActiveVariants && ! $item->product_variant_id) {
            return 'Ce produit possède maintenant plusieurs modèles/couleurs. Revenez sur la fiche produit, choisissez le modèle souhaité puis ajoutez-le de nouveau au panier.';
        }

        if ($item->product_variant_id) {
            $validVariant = $lockedProduct->variants()
                ->whereKey($item->product_variant_id)
                ->where('is_active', true)
                ->exists();

            if (! $validVariant) {
                return 'Le modèle/couleur sélectionné pour ' . $lockedProduct->name . ' n’est plus disponible. Revenez sur la fiche produit pour choisir un autre modèle.';
            }
        }

        if (($item->price_source ?? 'catalog') === 'negotiated') {
            $item->loadMissing('negotiation');

            if (! $this->hasValidNegotiation($item, $userId)) {
                return 'Le prix négocié de ' . $lockedProduct->name . ' n’est plus valide. Revenez au panier pour actualiser la ligne.';
            }

            return null;
        }

        $item->loadMissing('variant');
        $currentPublicPrice = (float) ($item->variant?->price_override ?? $lockedProduct->final_price ?? $lockedProduct->price ?? 0);

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
