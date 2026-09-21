<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShopLogisticsSettingsService
{
    public const MAX_GPS_ACCURACY_METERS = 50;

    public static function hasReliableStoredLocation(Shop $shop): bool
    {
        return $shop->latitude !== null && $shop->longitude !== null
            && in_array($shop->geo_status, [Shop::GEO_STATUS_RELIABLE, Shop::GEO_STATUS_VERIFIED], true)
            && ($shop->geo_source === 'logistics_verified'
                || ($shop->geo_source === 'manual_map' && $shop->geo_status === Shop::GEO_STATUS_VERIFIED)
                || ($shop->geo_source === 'browser_gps' && $shop->geo_accuracy > 0
                    && $shop->geo_accuracy <= self::MAX_GPS_ACCURACY_METERS));
    }

    public function __construct(private readonly SellerLogisticsValidator $validator) {}

    public function save(Shop $shop, array $input): Shop
    {
        $data = Validator::make($input, [
            'logistics_type' => ['required', Rule::in(['ovanie', 'seller'])],
            'expected_logistics_type' => ['required', Rule::in(['ovanie', 'seller'])],
        ])->validate();

        return DB::transaction(function () use ($shop, $input, $data) {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $previous = $shop->usesSellerLogistics() ? 'seller' : 'ovanie';
            if ($previous !== $data['expected_logistics_type']) {
                throw ValidationException::withMessages([
                    'logistics_type' => 'Le mode logistique a changé. Actualisez la page avant de continuer.',
                ]);
            }
            $changing = $previous !== $data['logistics_type'];
            if ($changing) {
                Validator::make($input, ['confirmed' => ['required', 'accepted']], [
                    'confirmed.accepted' => 'Confirmez le changement de mode logistique.',
                ])->validate();
            }

            if ($data['logistics_type'] === 'ovanie') {
                $location = Validator::make($input, [
                    'address' => ['required', 'string', 'max:255'],
                    'commune' => ['required', 'string', 'max:100'],
                    'district' => ['required', 'string', 'max:100'],
                    'landmark' => ['required', 'string', 'max:255'],
                    'latitude' => ['required', 'numeric', 'between:-90,90'],
                    'longitude' => ['required', 'numeric', 'between:-180,180'],
                    'location_confirmed' => ['required', 'accepted'],
                ], [
                    'latitude.*' => 'Localisez la boutique avant de continuer.',
                    'longitude.*' => 'Localisez la boutique avant de continuer.',
                ], [
                    'address' => 'adresse de la boutique', 'district' => 'quartier',
                    'landmark' => 'point de repère', 'location_confirmed' => 'confirmation de la position GPS',
                ])->validate();
                unset($location['location_confirmed']);
                foreach (['address', 'commune', 'district', 'landmark'] as $field) {
                    $location[$field] = trim($location[$field]);
                }
                $unchanged = collect($location)->every(fn ($value, $field) =>
                    in_array($field, ['latitude', 'longitude'], true)
                        ? abs((float) $shop->{$field} - (float) $value) < 0.0000001
                        : (string) $shop->{$field} === (string) $value);
                $capture = null;
                if ($changing || ! $unchanged || ! self::hasReliableStoredLocation($shop) || filled($input['geo_captured_at'] ?? null)) {
                    $capture = Validator::make($input, [
                        'geo_source' => ['required', Rule::in(['browser_gps', 'manual_map'])],
                        'geo_accuracy' => ['required_if:geo_source,browser_gps', 'nullable', 'numeric', 'gt:0', 'max:'.self::MAX_GPS_ACCURACY_METERS],
                        'geo_captured_at' => ['required', 'date', 'after_or_equal:'.now()->subMinutes(15)->toIso8601String(),
                            'before_or_equal:'.now()->addSeconds(10)->toIso8601String()],
                    ], [
                        'geo_source.*' => 'Capturez la position de la boutique depuis votre appareil.',
                        'geo_accuracy.*' => 'La localisation est trop imprécise. Réessayez depuis un téléphone à la boutique.',
                        'geo_captured_at.*' => 'La position doit être capturée à nouveau depuis la boutique.',
                    ])->validate();
                    $resolvedCapture = app(ShopPickupLocationService::class)->verify($shop, $input);
                    foreach (['city', 'region'] as $field) {
                        if (filled($resolvedCapture[$field] ?? null)) $location[$field] = $resolvedCapture[$field];
                    }
                }
                // Do not retain catalogue links or a staff verification for a moved shop.
                if ($shop->commune !== $location['commune']) {
                    $shop->commune_id = null;
                    $shop->quarter_id = null;
                    $shop->landmark_id = null;
                } elseif ($shop->district !== $location['district']) {
                    $shop->quarter_id = null;
                    $shop->landmark_id = null;
                } elseif ($shop->landmark !== $location['landmark']) {
                    $shop->landmark_id = null;
                }
                $shop->fill($location);
                if ($capture !== null) {
                    $shop->forceFill([
                        'geo_source' => $capture['geo_source'], 'geo_status' => Shop::GEO_STATUS_RELIABLE,
                        'geo_verified_at' => null, 'geo_accuracy' => $capture['geo_source'] === 'browser_gps' ? $capture['geo_accuracy'] : null,
                    ]);
                }
            }

            $shop->logistics_type = $data['logistics_type'];
            // The profile is the activation gate; retain every zone, price and delay,
            // including the seller's previous per-zone active/suspended choices.
            $shop->sellerDeliveryProfile()->update(['is_enabled' => $shop->usesSellerLogistics()]);
            $shop->load('sellerDeliveryProfile', 'sellerDeliveryZones');
            $validation = $this->validator->validate($shop);
            if (! $validation['complete']) {
                throw ValidationException::withMessages([
                    'logistics_type' => 'Complétez vos informations logistiques : '.implode(', ', $validation['missing']).'.',
                ]);
            }

            if ($changing) {
                $this->preserveLegacyOrderModes($shop, $previous);
            }
            $shop->logistics_status = $validation['status'];
            $shop->save();

            return $shop->refresh();
        });
    }

    private function preserveLegacyOrderModes(Shop $shop, string $previous): void
    {
        // Modern checkout already records both fields. Only fill missing legacy
        // snapshots before their shop fallback changes; never overwrite a snapshot.
        OrderItem::query()
            ->where(fn ($q) => $q->where('shop_id', $shop->id)->orWhere(fn ($legacy) =>
                $legacy->whereNull('shop_id')->whereHas('product', fn ($p) => $p->where('shop_id', $shop->id))))
            ->where(fn ($q) => $q->whereNull('delivery_provider')->orWhere('delivery_provider', '')
                ->orWhereNull('delivery_mode')->orWhere('delivery_mode', ''))
            ->lockForUpdate()->get()->each(function (OrderItem $item) use ($previous) {
                $provider = $item->delivery_provider ?: $item->delivery_mode ?: $item->shipment?->provider_type ?: $previous;
                $values = [];
                if (! filled($item->delivery_provider)) $values['delivery_provider'] = $provider;
                if (! filled($item->delivery_mode)) $values['delivery_mode'] = $provider;
                if ($values !== []) DB::table('order_items')->where('id', $item->id)->update($values);
            });
    }
}
