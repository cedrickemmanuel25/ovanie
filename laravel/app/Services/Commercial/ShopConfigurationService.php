<?php

namespace App\Services\Commercial;

use App\Models\AbidjanCommune;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\ShopConfigurationAssignment;
use App\Services\SellerLogisticsValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShopConfigurationService
{
    private const VEHICLE_CODES = ['moto', 'tricycle', 'pickup', 'camion_3t', 'camion_10t'];

    public function __construct(
        private readonly SellerLogisticsValidator $sellerValidator,
        private readonly ShopConfigurationAssignmentService $assignments,
    ) {}

    public function saveLogistics(ShopConfigurationAssignment $assignment, array $input): ShopConfigurationAssignment
    {
        abort_unless($assignment->configure_logistics, 403);
        $assignment = $this->assignments->start($assignment);

        $data = Validator::make($input, [
            'logistics_type' => ['required', Rule::in(['ovanie', 'seller'])],
            'location_confirmed' => ['required_if:logistics_type,ovanie', 'nullable', 'accepted'],
            'address' => ['required_if:logistics_type,ovanie', 'nullable', 'string', 'max:255'],
            'commune' => ['required_if:logistics_type,ovanie', 'nullable', 'string', 'max:120'],
            'district' => ['required_if:logistics_type,ovanie', 'nullable', 'string', 'max:120'],
            'landmark' => ['required_if:logistics_type,ovanie', 'nullable', 'string', 'max:255'],
            'latitude' => ['required_if:logistics_type,ovanie', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:logistics_type,ovanie', 'nullable', 'numeric', 'between:-180,180'],
            'default_delay' => ['required_if:logistics_type,seller', 'nullable', 'string', 'max:100'],
            'max_weight_kg' => ['required_if:logistics_type,seller', 'nullable', 'numeric', 'gt:0'],
            'max_volume_m3' => ['required_if:logistics_type,seller', 'nullable', 'numeric', 'gt:0'],
            'vehicle_types' => ['nullable', 'array'],
            'vehicle_types.*' => ['string', Rule::in(self::VEHICLE_CODES)],
            'conditions' => ['required_if:logistics_type,seller', 'nullable', 'string', 'max:2000'],
            'zones' => ['required_if:logistics_type,seller', 'nullable', 'array', 'min:1'],
            'zones.*.commune_id' => ['required', 'integer', 'exists:abidjan_communes,id'],
            'zones.*.vehicle_code' => ['required', Rule::in(self::VEHICLE_CODES)],
            'zones.*.delivery_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'zones.*.estimated_delay' => ['required', 'string', 'max:100'],
        ], [
            'location_confirmed.accepted' => 'Confirmez la position de la boutique avec le vendeur.',
            'zones.required_if' => 'Ajoutez au moins une commune et son tarif pour la logistique vendeur.',
        ])->validate();

        DB::transaction(function () use ($assignment, $data) {
            $shop = Shop::query()->lockForUpdate()->findOrFail($assignment->shop_id);
            $previous = $shop->usesSellerLogistics() ? 'seller' : 'ovanie';
            $newMode = $data['logistics_type'];

            if ($newMode === 'ovanie') {
                $shop->forceFill([
                    'logistics_type' => 'ovanie',
                    'address' => trim((string) $data['address']),
                    'commune' => trim((string) $data['commune']),
                    'district' => trim((string) $data['district']),
                    'landmark' => trim((string) $data['landmark']),
                    'latitude' => (float) $data['latitude'],
                    'longitude' => (float) $data['longitude'],
                    'geo_source' => 'commercial_mission',
                    'geo_status' => Shop::GEO_STATUS_RELIABLE,
                    'geo_verified_at' => now(),
                    'logistics_status' => Shop::LOGISTICS_READY,
                    'logistics_confirmed_at' => now(),
                ])->save();

                if ($shop->sellerDeliveryProfile) {
                    $shop->sellerDeliveryProfile()->update(['is_enabled' => false]);
                }
            } else {
                $shop->forceFill(['logistics_type' => 'seller'])->save();
                $profile = $shop->sellerDeliveryProfile()->updateOrCreate(
                    ['shop_id' => $shop->id],
                    [
                        'is_enabled' => true,
                        'default_delay' => trim((string) $data['default_delay']),
                        'max_weight_kg' => (float) $data['max_weight_kg'],
                        'max_volume_m3' => (float) $data['max_volume_m3'],
                        'vehicle_types' => array_values($data['vehicle_types'] ?? []),
                        'conditions' => trim((string) $data['conditions']),
                        'status' => 'pending_review',
                    ]
                );

                foreach ($data['zones'] as $zone) {
                    $commune = AbidjanCommune::query()->where('is_active', true)->findOrFail((int) $zone['commune_id']);
                    $shop->sellerDeliveryZones()->updateOrCreate(
                        ['commune_id' => $commune->id, 'vehicle_code' => $zone['vehicle_code']],
                        [
                            'seller_delivery_profile_id' => $profile->id,
                            'city' => 'Abidjan',
                            'commune' => $commune->name,
                            'district' => null,
                            'coverage_type' => 'commune',
                            'delivery_price' => (float) $zone['delivery_price'],
                            'estimated_delay' => trim((string) $zone['estimated_delay']),
                            'is_active' => true,
                        ]
                    );
                }

                $shop->load('sellerDeliveryProfile', 'sellerDeliveryZones');
                $validation = $this->sellerValidator->validate($shop);
                if (! $validation['complete']) {
                    throw ValidationException::withMessages([
                        'logistics_type' => 'Configuration vendeur incomplète : '.implode(', ', $validation['missing']).'.',
                    ]);
                }

                $shop->forceFill([
                    'logistics_status' => Shop::LOGISTICS_READY,
                    'logistics_confirmed_at' => now(),
                ])->save();
            }

            if ($previous !== $newMode) {
                $this->preserveLegacyOrderModes($shop, $previous);
            }
        });

        return $this->assignments->sync($assignment->fresh('shop'));
    }

    public function savePayout(ShopConfigurationAssignment $assignment, array $input): ShopConfigurationAssignment
    {
        abort_unless($assignment->configure_payout, 403);
        $assignment = $this->assignments->start($assignment);

        $data = Validator::make($input, [
            'payment_mode' => ['required', Rule::in([Shop::PAYMENT_POST_DELIVERY, Shop::PAYMENT_WEEKLY])],
            'mm_operator' => ['required', Rule::in(['orange', 'mtn', 'wave', 'moov'])],
            'mm_number' => ['required', 'regex:/^[0-9+ ]{8,20}$/'],
            'mm_holder' => ['required', 'string', 'max:255'],
            'seller_confirmation' => ['required', 'accepted'],
        ], [
            'seller_confirmation.accepted' => 'Le vendeur doit confirmer les coordonnées de reversement avant enregistrement.',
        ])->validate();

        $shop = Shop::findOrFail($assignment->shop_id);
        $shop->forceFill([
            'payment_mode' => $data['payment_mode'],
            'mm_operator' => $data['mm_operator'],
            'mm_number' => preg_replace('/\s+/', '', (string) $data['mm_number']),
            'mm_holder' => trim((string) $data['mm_holder']),
            'direct_payment' => false,
            'payout_configured_at' => now(),
        ])->save();

        return $this->assignments->sync($assignment->fresh('shop'));
    }

    private function preserveLegacyOrderModes(Shop $shop, string $previous): void
    {
        OrderItem::query()
            ->where(fn ($q) => $q->where('shop_id', $shop->id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('shop_id')->whereHas('product', fn ($p) => $p->where('shop_id', $shop->id))))
            ->where(fn ($q) => $q->whereNull('delivery_provider')->orWhere('delivery_provider', '')
                ->orWhereNull('delivery_mode')->orWhere('delivery_mode', ''))
            ->lockForUpdate()
            ->get()
            ->each(function (OrderItem $item) use ($previous) {
                $provider = $item->delivery_provider ?: $item->delivery_mode ?: $item->shipment?->provider_type ?: $previous;
                $values = [];
                if (! filled($item->delivery_provider)) $values['delivery_provider'] = $provider;
                if (! filled($item->delivery_mode)) $values['delivery_mode'] = $provider;
                if ($values !== []) DB::table('order_items')->where('id', $item->id)->update($values);
            });
    }
}
