<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Boutiques qui livrent elles-mêmes leurs commandes (« logistique vendeur »).
 *
 * La Logistique y repère celles qui n'ont pas terminé le réglage de leurs
 * livraisons : tant qu'il manque un élément, leurs clients ne peuvent pas être
 * livrés correctement. Elle peut alors relancer le vendeur.
 */
class LogisticsSellerDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('etat') === 'toutes' ? 'toutes' : 'a-configurer';
        $search = trim((string) $request->query('q', ''));

        $sellerShops = fn (): Builder => Shop::query()->where('logistics_type', 'seller');

        $total = $sellerShops()->count();
        $toConfigure = $sellerShops()->needsLogisticsConfiguration()->count();

        $shops = $sellerShops()
            ->with(['user:id,name,email,phone', 'sellerDeliveryProfile', 'sellerDeliveryZones'])
            ->when($filter === 'a-configurer', fn (Builder $query) => $query->needsLogisticsConfiguration())
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(function (Builder $match) use ($like) {
                    $match->where('name', 'like', $like)
                        ->orWhere('commune', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $shops->getCollection()->transform(function (Shop $shop) {
            $shop->setAttribute('delivery_gaps', $this->gaps($shop));

            return $shop;
        });

        return view('logistics.seller-delivery', [
            'shops' => $shops,
            'filter' => $filter,
            'search' => $search,
            'summary' => [
                'total' => $total,
                'to_configure' => $toConfigure,
                'ready' => max(0, $total - $toConfigure),
            ],
        ]);
    }

    /**
     * Ce qu'il reste à régler pour qu'une boutique puisse livrer elle-même.
     *
     * @return list<string>
     */
    private function gaps(Shop $shop): array
    {
        $gaps = [];

        if (! $shop->logistics_confirmed_at || $shop->logistics_status !== Shop::LOGISTICS_READY) {
            $gaps[] = 'Mode de livraison non confirmé';
        }

        $profile = $shop->sellerDeliveryProfile;
        if (! $profile || ! $profile->is_enabled) {
            $gaps[] = 'Conditions de livraison non renseignées';
        } else {
            if (blank($profile->default_delay)) {
                $gaps[] = 'Délai de livraison manquant';
            }
            if (blank($profile->conditions)) {
                $gaps[] = 'Conditions de livraison manquantes';
            }
            if ((float) $profile->max_weight_kg <= 0 || (float) $profile->max_volume_m3 <= 0) {
                $gaps[] = 'Poids ou volume maximum manquant';
            }
        }

        $pricedZones = $shop->sellerDeliveryZones
            ->filter(fn ($zone) => $zone->is_active && filled($zone->commune) && $zone->delivery_price !== null && filled($zone->estimated_delay));
        if ($pricedZones->isEmpty()) {
            $gaps[] = $shop->sellerDeliveryZones->isEmpty()
                ? 'Aucune zone de livraison'
                : 'Aucune zone active avec un tarif et un délai';
        }

        return $gaps;
    }
}
