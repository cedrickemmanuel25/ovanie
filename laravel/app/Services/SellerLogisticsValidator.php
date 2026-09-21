<?php

namespace App\Services;

use App\Models\Shop;

class SellerLogisticsValidator
{
    public function validate(Shop $shop): array
    {
        $shop->loadMissing('sellerDeliveryProfile', 'sellerDeliveryZones');

        if ($shop->usesOvanieLogistics()) {
            $missing = [];

            foreach ([
                'address' => 'adresse boutique',
                'commune' => 'commune',
                'district' => 'quartier',
                'landmark' => 'point de repère',
                'latitude' => 'latitude GPS',
                'longitude' => 'longitude GPS',
            ] as $field => $label) {
                if ($shop->{$field} === null || $shop->{$field} === '') {
                    $missing[] = $label;
                }
            }

            if ($missing === [] && $shop->geo_status === Shop::GEO_STATUS_VERIFICATION_REQUIRED) {
                $missing[] = 'confirmation de la position de la boutique';
            }

            return [
                'complete' => $missing === [],
                'missing' => $missing,
                'status' => $missing === [] ? Shop::LOGISTICS_READY : Shop::LOGISTICS_INCOMPLETE,
            ];
        }

        $profile = $shop->sellerDeliveryProfile;
        $missing = [];

        if (! $profile || ! $profile->is_enabled) {
            $missing[] = 'profil logistique vendeur actif';
        }

        if (! filled($profile?->default_delay)) {
            $missing[] = 'délai par défaut';
        }

        if ((float) $profile?->max_weight_kg <= 0) {
            $missing[] = 'poids maximum';
        }

        if ((float) $profile?->max_volume_m3 <= 0) {
            $missing[] = 'volume maximum';
        }

        if (! filled($profile?->conditions)) {
            $missing[] = 'conditions de livraison';
        }

        $hasZone = $shop->sellerDeliveryZones
            ->where('is_active', true)
            ->filter(fn ($zone) => filled($zone->commune)
                && $zone->delivery_price !== null
                && filled($zone->estimated_delay))
            ->isNotEmpty();

        if (! $hasZone) {
            $missing[] = 'au moins une commune livrée avec tarif et délai';
        }

        if ($shop->sellerDeliveryZones->where('is_active', true)->contains(fn ($zone) =>
            ! filled($zone->commune) || $zone->delivery_price === null
            || (float) $zone->delivery_price < 0 || ! filled($zone->estimated_delay))) {
            $missing[] = 'commune, tarif et délai valides pour chaque zone active';
        }

        return [
            'complete' => $missing === [],
            'missing' => $missing,
            'status' => $missing === [] ? Shop::LOGISTICS_READY : Shop::LOGISTICS_INCOMPLETE,
        ];
    }

    /**
     * La configuration logistique ne doit jamais désactiver la boutique.
     * Elle contrôle uniquement la possibilité de publier des produits.
     */
    public function synchronizeShopStatus(Shop $shop): Shop
    {
        $validation = $this->validate($shop);

        $shop->forceFill([
            'logistics_status' => $validation['status'],
        ])->save();

        return $shop->refresh();
    }
}
