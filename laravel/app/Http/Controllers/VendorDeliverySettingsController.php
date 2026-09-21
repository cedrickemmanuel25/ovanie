<?php

namespace App\Http\Controllers;

use App\Models\SellerDeliveryZone;
use App\Models\AbidjanCommune;
use App\Services\SellerLogisticsValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorDeliverySettingsController extends Controller
{
    private const VEHICLE_CODES = ['moto', 'tricycle', 'pickup', 'camion_3t', 'camion_10t'];

    private const VEHICLE_CAPACITIES = [
        'moto' => ['weight' => 20.0, 'volume' => 0.18],
        'tricycle' => ['weight' => 250.0, 'volume' => 1.5],
        'pickup' => ['weight' => 1000.0, 'volume' => 7.0],
        'camion_3t' => ['weight' => 3000.0, 'volume' => 20.0],
        'camion_10t' => ['weight' => null, 'volume' => 60.0],
    ];

    public function index(SellerLogisticsValidator $validator)
    {
        $shop = $this->sellerLogisticsShop();
        $shop->loadMissing('sellerDeliveryProfile', 'sellerDeliveryZones');

        $zones = $shop->sellerDeliveryZones
            ->sortBy(fn ($zone) => mb_strtolower(($zone->city ?: '').'|'.($zone->commune ?: '')))
            ->values();

        $validation = $validator->validate($shop);

        $activeZones = $zones->where('is_active', true);
        $stats = [
            'active_communes' => $activeZones->pluck('commune')->filter()->map(fn ($value) => mb_strtolower(trim((string) $value)))->unique()->count(),
            'min_price' => $activeZones->isNotEmpty() ? (float) $activeZones->min('delivery_price') : null,
            'max_price' => $activeZones->isNotEmpty() ? (float) $activeZones->max('delivery_price') : null,
        ];

        return view('vendor.delivery.index', compact('shop', 'zones', 'validation', 'stats'));
    }

    public function edit()
    {
        $shop = $this->sellerLogisticsShop();
        $shop->loadMissing('sellerDeliveryProfile', 'sellerDeliveryZones');

        $zones = $shop->sellerDeliveryZones
            ->sortBy(fn ($zone) => mb_strtolower(($zone->city ?: '').'|'.($zone->commune ?: '')))
            ->values();

        $communes = AbidjanCommune::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('vendor.delivery.edit', compact('shop', 'zones', 'communes'));
    }

    /**
     * Enregistre uniquement les capacités de la logistique vendeur.
     * Le choix OVANIE Logistics / logistique vendeur est fait pendant l'ouverture
     * de la boutique et ne doit pas être modifié depuis cette page.
     */
    public function update(Request $request, SellerLogisticsValidator $validator)
    {
        $shop = $this->sellerLogisticsShop();

        $data = $request->validate([
            'default_delay' => ['required', 'string', 'max:100'],
            'max_weight_kg' => ['required', 'numeric', 'gt:0'],
            'max_volume_m3' => ['required', 'numeric', 'gt:0'],
            'vehicle_types' => ['nullable', 'array'],
            'vehicle_types.*' => ['string', 'distinct', 'in:moto,tricycle,pickup,camion_3t,camion_10t'],
            'capacity_description' => ['nullable', 'string', 'max:255'],
            'conditions' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($shop, $data) {
            $shop->sellerDeliveryProfile()->updateOrCreate(
                ['shop_id' => $shop->id],
                [
                    'is_enabled' => true,
                    'default_delay' => trim($data['default_delay']),
                    'max_weight_kg' => $data['max_weight_kg'],
                    'max_volume_m3' => $data['max_volume_m3'],
                    'vehicle_types' => array_values($data['vehicle_types'] ?? []),
                    'capacity_description' => filled($data['capacity_description'] ?? null)
                        ? trim($data['capacity_description'])
                        : null,
                    'conditions' => trim($data['conditions']),
                    'status' => 'pending_review',
                ]
            );
        });

        $validator->synchronizeShopStatus($shop->refresh());

        return redirect()
            ->route('vendor.delivery.edit')
            ->with('success', 'Capacités logistiques enregistrées. Configurez maintenant vos tarifs par commune.');
    }

    /**
     * Une ligne de la grille tarifaire correspond à une commune.
     * Le moteur de checkout utilisera le tarif et le délai de la commune
     * correspondant à l'adresse de livraison du client.
     */
    public function storeZone(Request $request, SellerLogisticsValidator $validator)
    {
        $shop = $this->sellerLogisticsShop();
        $data = $this->validateCommuneRate($request);

        $profile = $shop->sellerDeliveryProfile()->firstOrCreate(
            ['shop_id' => $shop->id],
            ['is_enabled' => true, 'status' => 'incomplete']
        );

        $this->ensureUniqueRate($shop->id, $data['commune_id'], $data['vehicle_code']);

        $shop->sellerDeliveryZones()->create($data + [
            'seller_delivery_profile_id' => $profile->id,
            'district' => null,
            'coverage_type' => 'commune',
            'is_active' => true,
        ]);

        $validator->synchronizeShopStatus($shop->refresh());

        return redirect()
            ->route('vendor.delivery.edit')
            ->with('success', 'Tarif de livraison ajouté pour la commune de '.$data['commune'].'.');
    }

    public function updateZone(Request $request, $zone, SellerLogisticsValidator $validator)
    {
        $shop = $this->sellerLogisticsShop();

        $zone = SellerDeliveryZone::query()
            ->where('shop_id', $shop->id)
            ->findOrFail($zone);

        $data = $this->validateCommuneRate($request);
        $this->ensureUniqueRate($shop->id, $data['commune_id'], $data['vehicle_code'], $zone->id);

        $zone->update($data + [
            'district' => null,
            'coverage_type' => 'commune',
            'is_active' => $request->boolean('is_active'),
        ]);

        $validator->synchronizeShopStatus($shop->refresh());

        return redirect()
            ->route('vendor.delivery.edit')
            ->with('success', 'Tarif de la commune de '.$data['commune'].' mis à jour.');
    }

    public function destroyZone($zone, SellerLogisticsValidator $validator)
    {
        $shop = $this->sellerLogisticsShop();

        SellerDeliveryZone::query()
            ->where('shop_id', $shop->id)
            ->findOrFail($zone)
            ->delete();

        $validator->synchronizeShopStatus($shop->refresh());

        return redirect()
            ->route('vendor.delivery.edit')
            ->with('success', 'Commune retirée de votre grille tarifaire.');
    }

    private function validateCommuneRate(Request $request): array
    {
        $data = $request->validate([
            'commune_id' => ['required', 'integer', 'exists:abidjan_communes,id'],
            'vehicle_code' => ['required', 'string', 'in:' . implode(',', self::VEHICLE_CODES)],
            'delivery_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'estimated_delay' => ['required', 'string', 'max:100'],
            'zone_max_weight_kg' => ['nullable', 'numeric', 'gt:0'],
            'zone_max_volume_m3' => ['nullable', 'numeric', 'gt:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $commune = AbidjanCommune::query()->where('is_active', true)->findOrFail($data['commune_id']);
        $data['city'] = 'Abidjan';
        $data['commune'] = $commune->name;
        $data['estimated_delay'] = trim($data['estimated_delay']);
        $capacity = self::VEHICLE_CAPACITIES[$data['vehicle_code']];
        $data['max_weight_kg'] = $data['zone_max_weight_kg'] ?? $capacity['weight'];
        $data['max_volume_m3'] = $data['zone_max_volume_m3'] ?? $capacity['volume'];
        unset($data['zone_max_weight_kg'], $data['zone_max_volume_m3']);

        return $data;
    }

    private function ensureUniqueRate(
        int $shopId,
        int $communeId,
        string $vehicleCode,
        ?int $exceptId = null
    ): void {
        $query = SellerDeliveryZone::query()
            ->where('shop_id', $shopId)
            ->where('commune_id', $communeId)
            ->where('vehicle_code', $vehicleCode);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'vehicle_code' => 'Cette commune possède déjà un tarif pour ce véhicule. Modifiez la ligne existante.',
            ]);
        }
    }

    private function sellerLogisticsShop()
    {
        $shop = Auth::user()->shop()->firstOrFail();

        // La page Livraison est réservée aux vendeurs qui gèrent eux-mêmes la livraison.
        abort_unless($shop->usesSellerLogistics(), 404);

        return $shop;
    }
}
