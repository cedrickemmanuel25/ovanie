<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class PublicProductVisibilityService
{
    public function query(array $with = ['category', 'images'], bool $includeOrderable = false): Builder
    {
        $query = Product::query()->with($with);

        return $this->apply($query, $includeOrderable);
    }

    /**
     * Règles publiques communes à l'accueil, au catalogue et à la recherche.
     *
     * Un produit public peut être :
     * - en stock immédiat (stock > 0) ;
     * - disponible sur commande ;
     * - en précommande.
     *
     * Les produits explicitement en rupture temporaire restent masqués du
     * catalogue public. Les contrôleurs panier/checkout gardent leurs propres
     * contrôles transactionnels de quantité pour les produits en stock.
     */
    public function apply(Builder $query, bool $includeOrderable = false): Builder
    {
        if (Schema::hasColumn('products', 'status')) {
            $query->whereIn('products.status', ['actif', 'active', 'approved']);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where(function (Builder $active) {
                $active->whereNull('products.is_active')
                    ->orWhere('products.is_active', 1);
            });
        }

        if (Schema::hasColumn('products', 'archived_at')) {
            $query->whereNull('products.archived_at');
        }

        $hasStock = Schema::hasColumn('products', 'stock');
        $hasAvailability = Schema::hasColumn('products', 'availability_status');

        if ($includeOrderable && ($hasStock || $hasAvailability)) {
            $query->where(function (Builder $availability) use ($hasStock, $hasAvailability) {
                if ($hasStock) {
                    $availability->where('products.stock', '>', 0);
                }

                if ($hasAvailability) {
                    $method = $hasStock ? 'orWhereIn' : 'whereIn';
                    $availability->{$method}('products.availability_status', ['on_order', 'preorder']);
                }
            });
        } elseif ($hasStock) {
            $query->where('products.stock', '>', 0);
        }

        if (Schema::hasTable('shops')) {
            $query->whereHas('shop', function (Builder $shop) {
                // Source de vérité unique : la visibilité publique exige exactement
                // la même préparation commerciale/logistique que l'ajout au panier.
                $shop->canPublishProducts();
            });
        }

        return $query;
    }
}
