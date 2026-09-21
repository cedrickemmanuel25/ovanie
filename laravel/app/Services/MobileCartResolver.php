<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

/**
 * Résout le panier réellement utilisé par l'utilisateur connecté.
 *
 * Certaines versions historiques d'OVANIE ont utilisé user_id, d'autres
 * client_id, et certains modèles User exposent directement une relation cart.
 * Le checkout mobile ne doit donc jamais supposer une seule clé propriétaire.
 */
class MobileCartResolver
{
    /**
     * Retourne en priorité un panier non vide appartenant à l'utilisateur.
     */
    public function forUser(User $user, bool $lockForUpdate = false): ?Cart
    {
        foreach (['cart', 'shoppingCart', 'activeCart', 'carts'] as $relationName) {
            $cart = $this->fromUserRelation($user, $relationName, $lockForUpdate);
            if ($cart) {
                return $cart;
            }
        }

        $model = new Cart();
        $table = $model->getTable();
        $ownerColumns = array_values(array_filter(
            ['user_id', 'client_id'],
            fn (string $column) => Schema::hasColumn($table, $column)
        ));

        if ($ownerColumns === []) {
            return null;
        }

        $base = Cart::query()->with('items.product.shop');
        $base->where(function ($query) use ($ownerColumns, $user) {
            foreach ($ownerColumns as $index => $column) {
                if ($index === 0) {
                    $query->where($column, $user->id);
                } else {
                    $query->orWhere($column, $user->id);
                }
            }
        });

        // Le panier utilisé pour un checkout doit être celui qui possède des
        // lignes. Cela évite de tomber sur un ancien panier vide plus récent.
        $nonEmpty = clone $base;
        $nonEmpty->whereHas('items')->orderByDesc($model->getKeyName());
        if ($lockForUpdate) {
            $nonEmpty->lockForUpdate();
        }
        $cart = $nonEmpty->first();
        if ($cart) {
            return $cart;
        }

        $fallback = clone $base;
        $fallback->orderByDesc($model->getKeyName());
        if ($lockForUpdate) {
            $fallback->lockForUpdate();
        }

        return $fallback->first();
    }

    private function fromUserRelation(User $user, string $relationName, bool $lockForUpdate): ?Cart
    {
        if (! method_exists($user, $relationName)) {
            return null;
        }

        try {
            $relation = $user->{$relationName}();
            if (! $relation instanceof Relation || ! $relation->getRelated() instanceof Cart) {
                return null;
            }

            $query = $relation->getQuery()->with('items.product.shop');
            $query->whereHas('items')->orderByDesc((new Cart())->getKeyName());
            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $cart = $query->first();
            if ($cart) {
                return $cart;
            }

            $fallback = $relation->getQuery()->with('items.product.shop');
            $fallback->orderByDesc((new Cart())->getKeyName());
            if ($lockForUpdate) {
                $fallback->lockForUpdate();
            }

            return $fallback->first();
        } catch (\Throwable) {
            // Une relation historique mal configurée ne doit pas empêcher les
            // fallbacks par colonnes user_id/client_id ci-dessus.
            return null;
        }
    }
}
