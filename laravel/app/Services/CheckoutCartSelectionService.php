<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Applique au panier Laravel la sélection réellement choisie par le client.
 *
 * Le Web transporte des ids de lignes de panier, tandis que l'application
 * mobile connaît surtout les ids produit. Ce service permet aux deux canaux
 * de travailler sur le même panier sans commander silencieusement les lignes
 * que le client a décochées.
 */
class CheckoutCartSelectionService
{
    /**
     * @param  array<int, int|string>  $productIds
     * @param  array<int, int|string>  $cartItemIds
     */
    public function apply(Cart $cart, array $productIds = [], array $cartItemIds = [], bool $required = false): Cart
    {
        $cart->loadMissing('items.product.shop');

        $productIds = $this->positiveIds($productIds);
        $cartItemIds = $this->positiveIds($cartItemIds);

        if ($productIds === [] && $cartItemIds === []) {
            if ($required) {
                throw ValidationException::withMessages([
                    'cart' => 'Sélectionnez au moins un produit avant de passer la commande.',
                ]);
            }

            return $cart;
        }

        $selected = $cart->items->filter(function ($item) use ($productIds, $cartItemIds) {
            return ($cartItemIds !== [] && in_array((int) $item->id, $cartItemIds, true))
                || ($productIds !== [] && in_array((int) $item->product_id, $productIds, true));
        })->values();

        if ($selected->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Les produits sélectionnés ne sont plus disponibles dans votre panier.',
            ]);
        }

        $requestedCount = max(count($productIds), count($cartItemIds));
        if ($requestedCount > 0 && $selected->count() < $requestedCount) {
            throw ValidationException::withMessages([
                'cart' => 'Un ou plusieurs produits sélectionnés ne sont plus disponibles dans votre panier.',
            ]);
        }

        $cart->setRelation('items', $selected);

        return $cart;
    }

    /** @return array<int, int> */
    private function positiveIds(array $values): array
    {
        return Collection::make($values)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
