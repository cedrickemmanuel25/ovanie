<?php

namespace App\Services;

use App\Models\DeliveryDistanceMatrix;
use App\Models\DeliveryRouteCache;
use App\Models\DeliveryService;
use App\Models\DeliveryServiceRate;
use App\Services\Geo\RoutingService;
use App\Services\Geo\AbidjanLocationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DeliveryPricingEngine
{
    public const UNKNOWN_MESSAGE = 'Les frais de livraison ne peuvent pas etre calcules pour cette adresse. Veuillez modifier votre adresse ou votre panier.';

    public function __construct(
        private readonly DeliveryServicePricingService $servicePricing,
        private readonly OvanieDeliveryPriceCalculator $calculator,
        private readonly LogisticsVehicleResolver $vehicleResolver,
        private readonly RoutingService $routingService,
        private readonly AbidjanLocationResolver $locations
    ) {}

    public function quote(Collection $items, ?Request $request = null): array
    {
        $shop = $items->first()?->product?->shop;

        if (! $shop) {
            return $this->unavailable('Boutique introuvable.', 'shop_missing');
        }

        if ($request?->input('delivery_destination_type') === 'pickup') {
            return [
                'available' => true,
                'provider_type' => OrderWorkflowService::PROVIDER_PICKUP,
                'code' => 'pickup',
                'name' => 'Retrait gere par OVANIE',
                'delay' => 'Apres confirmation',
                'estimated_hours' => null,
                'price' => 0.0,
                'price_label' => '0 FCFA',
                'quote_required' => false,
                'delivery_service_id' => null,
                'delivery_zone_id' => null,
                'meta' => ['free_configured' => true],
            ];
        }

        if ($reason = $this->invalidLogisticsDataReason($items)) {
            return $shop->usesSellerLogistics()
                ? $this->sellerUnavailable($reason, [], 'product_logistics_missing')
                : $this->unavailable($reason, 'product_logistics_missing');
        }

        return $shop->usesSellerLogistics()
            ? $this->sellerQuote($items, $request)
            : $this->ovanieQuote($items, $request);
    }


    /**
     * Calcule une tournée OVANIE multi-points lorsque plusieurs boutiques OVANIE
     * peuvent être collectées dans le même transport.
     *
     * Retourne null si les coordonnées nécessaires ne permettent pas une tournée
     * fiable : le checkout conserve alors les devis séparés déjà calculés.
     */
    public function quoteConsolidated(Collection $groups, ?Request $request = null): ?array
    {
        if ($groups->count() < 2 || $request?->input('delivery_destination_type') === 'pickup') {
            return null;
        }

        $destinationLat = $request?->input('delivery_latitude') ?: $request?->input('delivery_lat');
        $destinationLng = $request?->input('delivery_longitude') ?: $request?->input('delivery_lng');

        if (! $this->validCoordinates($destinationLat, $destinationLng)) {
            return null;
        }

        $pickupPoints = $groups->map(function (array $group) {
            $shop = $group['shop'] ?? null;

            return [
                'shop_id' => (int) ($group['shop_id'] ?? 0),
                'shop_name' => $group['shop_name'] ?? 'Point de collecte',
                'address' => collect([
                    $shop?->address,
                    $shop?->landmark,
                    $shop?->district,
                    $shop?->commune,
                    $shop?->city,
                ])->filter()->implode(' - '),
                'commune' => $shop?->commune,
                'latitude' => $shop?->latitude,
                'longitude' => $shop?->longitude,
            ];
        })->filter(fn (array $point) => $this->validCoordinates($point['latitude'], $point['longitude']))
          ->values();

        if ($pickupPoints->count() !== $groups->count()) {
            return null;
        }

        $items = $groups
            ->flatMap(fn (array $group) => collect($group['items'] ?? []))
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        $route = $this->buildConsolidatedRoute(
            $pickupPoints,
            (float) $destinationLat,
            (float) $destinationLng
        );

        if (! $route) {
            return null;
        }

        $legacyRate = $this->legacyRate($items, $request);
        $metrics = $this->metrics($items);
        $vehicle = $this->vehicleResolver->resolve($metrics['weight_kg'], $metrics['volume_m3']);

        if (! $vehicle['rate_card']) {
            return null;
        }

        $calculation = $this->calculator->calculate($items, $route, [
            'origin_commune' => null,
            'destination_commune' => $request?->input('delivery_commune'),
            'destination_city' => $request?->input('delivery_city'),
            'delivery_zone' => $request?->input('delivery_zone'),
            'handling' => $request?->boolean('handling'),
            'unloading' => $request?->boolean('unloading'),
            'fragile' => $request?->boolean('fragile'),
            'urgent' => $request?->boolean('urgent'),
        ], $legacyRate);

        $delay = $this->delayText($calculation['duration_minutes'] ?? null);
        $consolidationCode = 'OVL-' . substr(sha1(
            $pickupPoints->pluck('shop_id')->sort()->implode('-')
            . '|' . round((float) $destinationLat, 5)
            . '|' . round((float) $destinationLng, 5)
        ), 0, 12);

        return [
            'available' => true,
            'provider_type' => OrderWorkflowService::PROVIDER_OVANIE,
            'code' => 'ovanie-consolidated',
            'name' => 'Livraison OVANIE Logistics',
            'delay' => $delay,
            'estimated_hours' => isset($calculation['duration_minutes'])
                ? max(1, (int) ceil(((int) $calculation['duration_minutes']) / 60))
                : null,
            'price' => $calculation['price'],
            'price_label' => number_format($calculation['price'], 0, ',', ' ') . ' FCFA',
            'quote_required' => false,
            'delivery_service_id' => $legacyRate?->delivery_service_id,
            'delivery_zone_id' => $legacyRate?->delivery_service_zone_id,
            'vehicle_code' => $calculation['vehicle_code'],
            'vehicle_label' => $calculation['vehicle_label'],
            'vehicle_type' => $calculation['vehicle_label'],
            'recommended_vehicle' => $calculation['vehicle_label'],
            'consolidation_code' => $consolidationCode,
            'meta' => [
                'consolidated' => true,
                'consolidation_code' => $consolidationCode,
                'pickup_points' => $pickupPoints->all(),
                'shop_ids' => $pickupPoints->pluck('shop_id')->all(),
                'distance_km' => $calculation['distance_km'],
                'duration_minutes' => $calculation['duration_minutes'],
                'traffic_delay_minutes' => $calculation['traffic_delay_minutes'],
                'no_traffic_duration_minutes' => $calculation['no_traffic_duration_minutes'],
                'vehicle_code' => $calculation['vehicle_code'],
                'vehicle_label' => $calculation['vehicle_label'],
                'weight_kg' => $calculation['weight_kg'],
                'volume_m3' => $calculation['volume_m3'],
                'components' => $calculation['components'],
                'calculation' => $calculation,
                'route' => $route,
                'client_summary' => [
                    'name' => 'Livraison OVANIE Logistics',
                    'price' => $calculation['price'],
                    'vehicle_label' => $calculation['vehicle_label'],
                    'delay' => $delay,
                ],
            ],
        ];
    }

    private function sellerQuote(Collection $items, ?Request $request): array
    {
        $shop = $items->first()?->product?->shop;
        $validation = app(SellerLogisticsValidator::class)->validate($shop);

        if (! $validation['complete']) {
            return [
                'available' => false,
                'provider_type' => OrderWorkflowService::PROVIDER_SELLER,
                'code' => 'seller-delivery-incomplete',
                'name' => 'Livraison vendeur',
                'delay' => null,
                'estimated_hours' => null,
                'price' => null,
                'price_label' => 'Livraison vendeur a configurer',
                'quote_required' => true,
                'issue_code' => 'seller_configuration_incomplete',
                'reason' => 'Configuration logistique vendeur incomplete : ' . implode(', ', $validation['missing']),
                'delivery_service_id' => null,
                'delivery_zone_id' => null,
                'meta' => [
                    'client_summary' => [
                        'name' => 'Livraison vendeur',
                        'price' => null,
                        'vehicle_label' => null,
                        'delay' => null,
                    ],
                    'missing' => $validation['missing'],
                ],
            ];
        }

        $metrics = $this->metrics($items);
        $profile = $shop->sellerDeliveryProfile;

        // Le panier reste regroupé par boutique, mais sa livraison peut nécessiter
        // plusieurs rotations. La capacité du profil est une capacité par trajet,
        // pas une raison de remplacer des produits ni de bloquer toute la commande.
        $tripCount = max(
            1,
            $this->requiredTrips($metrics['weight_kg'], $profile->max_weight_kg),
            $this->requiredTrips($metrics['volume_m3'], $profile->max_volume_m3)
        );
        $tripMetrics = [
            'weight_kg' => $metrics['weight_kg'] / $tripCount,
            'volume_m3' => $metrics['volume_m3'] / $tripCount,
        ];

        $requiredVehicle = $this->vehicleResolver->resolve($tripMetrics['weight_kg'], $tripMetrics['volume_m3']);
        $configuredVehicles = collect($profile->vehicle_types ?? [])->filter()->values();
        $candidateCodes = collect($this->vehicleResolver->codesFrom($requiredVehicle['code']))
            ->when($configuredVehicles->isNotEmpty(), fn ($codes) => $codes->filter(
                fn ($code) => $configuredVehicles->contains($code)
            ))
            ->values();

        $zone = null;
        $vehicleCode = null;

        foreach ($candidateCodes as $candidateCode) {
            $candidateZone = $this->sellerZone($shop, $request, $candidateCode, $tripMetrics);

            if ($candidateZone) {
                $zone = $candidateZone;
                $vehicleCode = $candidateCode;
                break;
            }
        }

        if (! $zone || ! $vehicleCode) {
            return $this->sellerUnavailable('Aucun tarif vendeur actif ne correspond a cette commune et a un vehicule capable de transporter la commande.', [], 'seller_rate_missing');
        }

        $vehicleLabel = $this->vehicleResolver->labelForCode($vehicleCode);
        $unitTripPrice = round((float) $zone->delivery_price, 0);
        $price = $unitTripPrice * $tripCount;
        $delay = $zone->estimated_delay ?: $profile->default_delay;

        return [
            'available' => true,
            'provider_type' => OrderWorkflowService::PROVIDER_SELLER,
            'code' => 'seller-delivery',
            'name' => 'Livraison vendeur',
            'delay' => $delay,
            'estimated_hours' => null,
            'price' => $price,
            'price_label' => number_format($price, 0, ',', ' ') . ' FCFA',
            'quote_required' => false,
            'delivery_service_id' => null,
            'delivery_zone_id' => $zone->id,
            'vehicle_code' => $vehicleCode,
            'vehicle_label' => $vehicleLabel,
            'vehicle_type' => $vehicleCode,
            'recommended_vehicle' => $vehicleLabel,
            'meta' => [
                'client_summary' => [
                    'name' => 'Livraison',
                    'price' => $price,
                    'vehicle_label' => $vehicleLabel,
                    'delay' => $delay,
                    'trip_count' => $tripCount,
                ],
                'seller_delivery' => [
                    'profile_id' => $profile->id,
                    'zone_id' => $zone->id,
                    'commune' => $zone->commune,
                    'district' => $zone->district,
                    'conditions' => $profile->conditions,
                    'weight_kg' => $metrics['weight_kg'],
                    'volume_m3' => $metrics['volume_m3'],
                    'vehicle_code' => $vehicleCode,
                    'vehicle_label' => $vehicleLabel,
                    'trip_count' => $tripCount,
                    'unit_trip_price' => $unitTripPrice,
                    'trip_weight_kg' => $tripMetrics['weight_kg'],
                    'trip_volume_m3' => $tripMetrics['volume_m3'],
                    'max_weight_kg' => $zone->max_weight_kg,
                    'max_volume_m3' => $zone->max_volume_m3,
                ],
            ],
        ];
    }

    private function ovanieQuote(Collection $items, ?Request $request): array
    {
        $legacyRate = $this->legacyRate($items, $request);
        $metrics = $this->metrics($items);
        $vehicle = $this->vehicleResolver->resolve($metrics['weight_kg'], $metrics['volume_m3']);
        $shop = $items->first()?->product?->shop;
        $destination = [
            'origin_commune' => $shop?->commune,
            'destination_commune' => $request?->input('delivery_commune'),
            'destination_city' => $request?->input('delivery_city'),
            'delivery_zone' => $request?->input('delivery_zone'),
            'handling' => $request?->boolean('handling'),
            'unloading' => $request?->boolean('unloading'),
            'fragile' => $request?->boolean('fragile'),
            'urgent' => $request?->boolean('urgent'),
        ];

        if (! $vehicle || empty($vehicle['is_active'])) {
            return $this->unavailable(
                'Aucun véhicule OVANIE actif ne peut transporter cette commande.',
                'ovanie_vehicle_unavailable'
            );
        }

        $hasCommunePair = filled($destination['origin_commune'])
            && filled($destination['destination_commune']);

        // Pour le checkout web ET mobile, le tarif configuré dans
        // Tarification > Tarifs communes est la source obligatoire dès qu'une
        // commune de départ et une commune d'arrivée sont connues.
        $fixedRelation = $hasCommunePair
            ? $this->calculator->fixedDestinationRelation($destination, $vehicle['code'])
            : null;

        if ($hasCommunePair && ! $fixedRelation) {
            return $this->unavailable(
                'Aucun tarif de livraison n’est configuré pour '
                .trim((string) $destination['origin_commune']).' → '
                .trim((string) $destination['destination_commune']).' avec le véhicule '.$vehicle['label'].'.',
                'commune_tariff_missing'
            );
        }

        // Le GPS reste utile pour l'ETA et le suivi, mais un tarif fixe commune
        // -> commune ne doit pas devenir indisponible simplement parce que le
        // fournisseur d'itinéraire ne répond pas. Dans ce cas, le prix provient
        // quand même de la matrice et aucune formule au kilomètre n'est utilisée.
        $route = $this->resolveRoute($items, $request);
        if (! $route && $fixedRelation) {
            $route = $this->gridOnlyRoute();
            $route['duration_minutes'] = data_get($fixedRelation, 'meta.estimated_delay_minutes');
        }

        if (! $route) {
            return $this->unavailable('Distance OVANIE impossible a calculer pour cette adresse.', 'route_unavailable');
        }

        // Les anciennes cartes base/km/kg/m³ ne sont nécessaires que pour les
        // cas historiques sans paire de communes explicite (ex. tournée
        // consolidée). Elles ne conditionnent plus un trajet normal tarifé dans
        // la matrice communale.
        if (! $hasCommunePair && ! $vehicle['rate_card']) {
            return $this->unavailable('Référence tarifaire OVANIE indisponible pour ce véhicule.', 'ovanie_reference_rate_missing');
        }

        $calculation = $this->calculator->calculate($items, $route, $destination, $legacyRate);

        if ($hasCommunePair && (
            ($calculation['rate_source'] ?? null) === 'missing_commune_tariff'
            || (float) ($calculation['price'] ?? 0) <= 0
        )) {
            return $this->unavailable(
                'Le tarif de cette relation n’est pas complètement configuré pour le véhicule requis.',
                'commune_tariff_missing'
            );
        }

        $delay = $this->delayText($calculation['duration_minutes'] ?? null);

        return [
            'available' => true,
            'provider_type' => OrderWorkflowService::PROVIDER_OVANIE,
            'code' => 'ovanie-logistics',
            'name' => 'Livraison OVANIE Logistics',
            'delay' => $delay,
            'estimated_hours' => isset($calculation['duration_minutes'])
                ? max(1, (int) ceil(((int) $calculation['duration_minutes']) / 60))
                : null,
            'price' => $calculation['price'],
            'price_label' => number_format($calculation['price'], 0, ',', ' ') . ' FCFA',
            'quote_required' => false,
            'delivery_service_id' => $legacyRate?->delivery_service_id,
            'delivery_zone_id' => $legacyRate?->delivery_service_zone_id,
            'vehicle_code' => $calculation['vehicle_code'],
            'vehicle_label' => $calculation['vehicle_label'],
            'vehicle_type' => $calculation['vehicle_label'],
            'recommended_vehicle' => $calculation['vehicle_label'],
            'meta' => [
                'distance_km' => $calculation['distance_km'],
                'duration_minutes' => $calculation['duration_minutes'],
                'traffic_delay_minutes' => $calculation['traffic_delay_minutes'],
                'no_traffic_duration_minutes' => $calculation['no_traffic_duration_minutes'],
                'vehicle_code' => $calculation['vehicle_code'],
                'vehicle_label' => $calculation['vehicle_label'],
                'weight_kg' => $calculation['weight_kg'],
                'volume_m3' => $calculation['volume_m3'],
                'components' => $calculation['components'],
                'client_summary' => [
                    'name' => 'Livraison OVANIE Logistics',
                    'price' => $calculation['price'],
                    'vehicle_label' => $calculation['vehicle_label'],
                    'delay' => $delay,
                ],
                'calculation' => $calculation,
                'route' => $route,
            ],
        ];
    }

    public function resolveDistanceKm(Collection $items, ?Request $request): ?float
    {
        return $this->resolveRoute($items, $request)['distance_km'] ?? null;
    }

    private function resolveRoute(Collection $items, ?Request $request): ?array
    {
        $shop = $items->first()?->product?->shop;
        $lat1 = $shop?->latitude;
        $lng1 = $shop?->longitude;
        $lat2 = $request?->input('delivery_latitude') ?: $request?->input('delivery_lat');
        $lng2 = $request?->input('delivery_longitude') ?: $request?->input('delivery_lng');

        if ($this->validCoordinates($lat1, $lng1) && $this->validCoordinates($lat2, $lng2)) {
            if ($cached = $this->cachedRoute($lat1, $lng1, $lat2, $lng2)) {
                return $cached;
            }

            $route = $this->routingService->route((float) $lat1, (float) $lng1, (float) $lat2, (float) $lng2);

            if ($route['success'] ?? false) {
                $route['source'] = $route['provider'] ?? 'tomtom';
                $this->storeRouteCache($lat1, $lng1, $lat2, $lng2, $route);
                return $route;
            }
        }

        if ($matrix = $this->matrixRoute($shop?->commune, $request?->input('delivery_commune') ?: $request?->input('delivery_city'))) {
            return $matrix;
        }

        if ($this->validCoordinates($lat1, $lng1) && $this->validCoordinates($lat2, $lng2)) {
            return [
                'success' => true,
                'provider' => 'haversine',
                'source' => 'haversine',
                'distance_km' => $this->haversineDistanceKm((float) $lat1, (float) $lng1, (float) $lat2, (float) $lng2),
                'duration_minutes' => null,
                'traffic_delay_minutes' => null,
                'no_traffic_duration_minutes' => null,
                'route_geometry' => null,
                'raw' => [],
            ];
        }

        return null;
    }

    private function legacyRate(Collection $items, ?Request $request): ?DeliveryServiceRate
    {
        $deliveryZone = trim((string) $request?->input('delivery_zone'));
        $city = $deliveryZone === 'interieur'
            ? trim((string) $request?->input('delivery_city'))
            : 'Abidjan';
        $commune = $deliveryZone === 'abidjan'
            ? trim((string) $request?->input('delivery_commune'))
            : '';

        $rates = DeliveryServiceRate::query()
            ->where('is_active', true)
            ->whereHas('service', function ($query) {
                $query->where('provider_type', DeliveryService::PROVIDER_OVANIE)
                    ->where('is_active', true);
            })
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->get();

        $normalize = static fn ($value): string => mb_strtolower(trim((string) $value));
        $deliveryZoneNormalized = $normalize($deliveryZone);
        $cityNormalized = $normalize($city);
        $communeNormalized = $normalize($commune);

        return $rates
            ->filter(function (DeliveryServiceRate $rate) use (
                $normalize,
                $deliveryZoneNormalized,
                $cityNormalized,
                $communeNormalized
            ) {
                $rateZone = $normalize($rate->delivery_zone);
                if ($rateZone !== '' && ($deliveryZoneNormalized === '' || $rateZone !== $deliveryZoneNormalized)) {
                    return false;
                }

                $rateCity = $normalize($rate->city);
                if ($rateCity !== '' && ($cityNormalized === '' || $rateCity !== $cityNormalized)) {
                    return false;
                }

                $rateCommune = $normalize($rate->commune);
                if ($rateCommune !== '' && ($communeNormalized === '' || $rateCommune !== $communeNormalized)) {
                    return false;
                }

                return true;
            })
            ->sortByDesc(function (DeliveryServiceRate $rate) {
                if (filled($rate->commune)) {
                    return 400;
                }

                if (filled($rate->city)) {
                    return 300;
                }

                if (filled($rate->delivery_zone)) {
                    return 200;
                }

                return 100;
            })
            ->first();
    }

    private function matrixRoute(?string $origin, ?string $destination): ?array
    {
        $origin = mb_strtolower(trim((string) $origin));
        $destination = mb_strtolower(trim((string) $destination));

        if ($origin === '' || $destination === '') {
            return null;
        }

        if ($origin === $destination) {
            return [
                'success' => true,
                'provider' => 'matrix',
                'source' => 'same_commune',
                'distance_km' => 0.01,
                'duration_minutes' => null,
                'traffic_delay_minutes' => null,
                'no_traffic_duration_minutes' => null,
                'route_geometry' => null,
                'raw' => [],
            ];
        }

        $row = DeliveryDistanceMatrix::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(origin_commune) = ?', [$origin])
            ->whereRaw('LOWER(destination_commune) = ?', [$destination])
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'success' => true,
            'provider' => 'matrix',
            'source' => 'commune_matrix',
            'distance_km' => (float) $row->distance_km,
            'duration_minutes' => $row->estimated_duration_minutes,
            'traffic_delay_minutes' => null,
            'no_traffic_duration_minutes' => null,
            'route_geometry' => null,
            'raw' => ['matrix_id' => $row->id],
        ];
    }

    private function cachedRoute(float $lat1, float $lng1, float $lat2, float $lng2): ?array
    {
        $cache = DeliveryRouteCache::query()
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where('origin_lat', round($lat1, 7))
            ->where('origin_lng', round($lng1, 7))
            ->where('destination_lat', round($lat2, 7))
            ->where('destination_lng', round($lng2, 7))
            ->latest()
            ->first();

        if (! $cache) {
            return null;
        }

        return [
            'success' => true,
            'provider' => $cache->provider,
            'source' => 'route_cache',
            'distance_km' => (float) $cache->distance_km,
            'duration_minutes' => $cache->duration_minutes,
            'traffic_delay_minutes' => $cache->traffic_delay_minutes,
            'no_traffic_duration_minutes' => $cache->no_traffic_duration_minutes,
            'route_geometry' => $cache->route_geometry,
            'raw' => $cache->raw_response ?: [],
        ];
    }

    private function storeRouteCache(float $lat1, float $lng1, float $lat2, float $lng2, array $route): void
    {
        DeliveryRouteCache::updateOrCreate([
            'provider' => $route['provider'] ?? 'tomtom',
            'origin_lat' => round($lat1, 7),
            'origin_lng' => round($lng1, 7),
            'destination_lat' => round($lat2, 7),
            'destination_lng' => round($lng2, 7),
        ], [
            'distance_km' => $route['distance_km'] ?? null,
            'duration_minutes' => $route['duration_minutes'] ?? null,
            'traffic_delay_minutes' => $route['traffic_delay_minutes'] ?? null,
            'no_traffic_duration_minutes' => $route['no_traffic_duration_minutes'] ?? null,
            'route_geometry' => $route['route_geometry'] ?? null,
            'raw_response' => $route['raw'] ?? [],
            'expires_at' => now()->addHours(12),
        ]);
    }


    private function buildConsolidatedRoute(Collection $pickupPoints, float $destinationLat, float $destinationLng): ?array
    {
        $remaining = $pickupPoints->values();
        $start = $remaining->sortByDesc(fn (array $point) => $this->haversineDistanceKm(
            (float) $point['latitude'],
            (float) $point['longitude'],
            $destinationLat,
            $destinationLng
        ))->first();

        if (! $start) {
            return null;
        }

        $ordered = collect([$start]);
        $remaining = $remaining->reject(fn (array $point) => (int) $point['shop_id'] === (int) $start['shop_id'])->values();
        $current = $start;

        while ($remaining->isNotEmpty()) {
            $next = $remaining->sortBy(fn (array $point) => $this->haversineDistanceKm(
                (float) $current['latitude'],
                (float) $current['longitude'],
                (float) $point['latitude'],
                (float) $point['longitude']
            ))->first();

            $ordered->push($next);
            $remaining = $remaining->reject(fn (array $point) => (int) $point['shop_id'] === (int) $next['shop_id'])->values();
            $current = $next;
        }

        $legs = [];
        $totalDistance = 0.0;
        $totalDuration = 0;
        $totalTrafficDelay = 0;
        $totalNoTrafficDuration = 0;

        for ($index = 0; $index < $ordered->count(); $index++) {
            $from = $ordered[$index];
            $to = $index + 1 < $ordered->count()
                ? $ordered[$index + 1]
                : [
                    'shop_id' => null,
                    'shop_name' => 'Destination client',
                    'latitude' => $destinationLat,
                    'longitude' => $destinationLng,
                ];

            $leg = $this->resolveCoordinateRoute(
                (float) $from['latitude'],
                (float) $from['longitude'],
                (float) $to['latitude'],
                (float) $to['longitude']
            );

            if (! $leg) {
                return null;
            }

            $totalDistance += (float) ($leg['distance_km'] ?? 0);
            $totalDuration += (int) ($leg['duration_minutes'] ?? 0);
            $totalTrafficDelay += (int) ($leg['traffic_delay_minutes'] ?? 0);
            $totalNoTrafficDuration += (int) ($leg['no_traffic_duration_minutes'] ?? 0);
            $legs[] = [
                'from_shop_id' => $from['shop_id'],
                'to_shop_id' => $to['shop_id'],
                'distance_km' => $leg['distance_km'] ?? 0,
                'duration_minutes' => $leg['duration_minutes'] ?? null,
                'provider' => $leg['provider'] ?? null,
                'source' => $leg['source'] ?? null,
            ];
        }

        return [
            'success' => true,
            'provider' => 'multi_route',
            'source' => 'ovanie_consolidated_route',
            'distance_km' => round($totalDistance, 2),
            'duration_minutes' => $totalDuration > 0 ? $totalDuration : null,
            'traffic_delay_minutes' => $totalTrafficDelay > 0 ? $totalTrafficDelay : null,
            'no_traffic_duration_minutes' => $totalNoTrafficDuration > 0 ? $totalNoTrafficDuration : null,
            'route_geometry' => null,
            'raw' => [
                'pickup_order' => $ordered->pluck('shop_id')->all(),
                'legs' => $legs,
            ],
        ];
    }

    private function resolveCoordinateRoute(float $lat1, float $lng1, float $lat2, float $lng2): ?array
    {
        if ($cached = $this->cachedRoute($lat1, $lng1, $lat2, $lng2)) {
            return $cached;
        }

        $route = $this->routingService->route($lat1, $lng1, $lat2, $lng2);

        if ($route['success'] ?? false) {
            $route['source'] = $route['provider'] ?? 'routing';
            $this->storeRouteCache($lat1, $lng1, $lat2, $lng2, $route);
            return $route;
        }

        return [
            'success' => true,
            'provider' => 'haversine',
            'source' => 'haversine',
            'distance_km' => $this->haversineDistanceKm($lat1, $lng1, $lat2, $lng2),
            'duration_minutes' => null,
            'traffic_delay_minutes' => null,
            'no_traffic_duration_minutes' => null,
            'route_geometry' => null,
            'raw' => [],
        ];
    }

    /**
     * Route technique utilisée uniquement lorsqu'un tarif fixe commune +
     * véhicule existe. Aucun coût kilométrique n'est calculé avec cette route.
     */
    private function gridOnlyRoute(): array
    {
        return [
            'success' => true,
            'provider' => 'delivery_grid',
            'source' => 'fixed_destination_vehicle_grid',
            'distance_km' => 0.0,
            'duration_minutes' => null,
            'traffic_delay_minutes' => null,
            'no_traffic_duration_minutes' => null,
            'route_geometry' => null,
            'raw' => [],
        ];
    }

    private function unavailable(string $reason, string $issueCode = 'delivery_unavailable'): array
    {
        return [
            'available' => false,
            'provider_type' => OrderWorkflowService::PROVIDER_OVANIE,
            'code' => 'ovanie-delivery-to-confirm',
            'name' => 'Livraison OVANIE Logistics',
            'delay' => null,
            'estimated_hours' => null,
            'price' => null,
            'price_label' => 'Livraison a confirmer par OVANIE',
            'quote_required' => true,
            'issue_code' => $issueCode,
            'reason' => $reason,
            'meta' => [
                'client_summary' => [
                    'name' => 'Livraison OVANIE Logistics',
                    'price' => null,
                    'vehicle_label' => null,
                    'delay' => null,
                ],
            ],
        ];
    }

    private function sellerUnavailable(string $reason, array $missing = [], string $issueCode = 'seller_delivery_unavailable'): array
    {
        return [
            'available' => false,
            'provider_type' => OrderWorkflowService::PROVIDER_SELLER,
            'code' => 'seller-delivery-unavailable',
            'name' => 'Livraison vendeur',
            'delay' => null,
            'estimated_hours' => null,
            'price' => null,
            'price_label' => 'Livraison vendeur indisponible',
            'quote_required' => true,
            'issue_code' => $issueCode,
            'reason' => $reason,
            'delivery_service_id' => null,
            'delivery_zone_id' => null,
            'meta' => [
                'client_summary' => [
                    'name' => 'Livraison vendeur',
                    'price' => null,
                    'vehicle_label' => null,
                    'delay' => null,
                ],
                'missing' => $missing,
            ],
        ];
    }

    private function sellerZone($shop, ?Request $request, string $vehicleCode, array $metrics)
    {
        $city = $this->normalizeLocation($request?->input('delivery_city'));
        $commune = $this->normalizeLocation($request?->input('delivery_commune'));
        $destinationCommune = $commune !== '' ? $commune : $city;

        if ($destinationCommune === '') {
            return null;
        }

        $zones = $shop->sellerDeliveryZones()
            ->where('is_active', true)
            ->get();

        $communeZones = $zones->filter(function ($zone) use ($city, $destinationCommune) {
            $zoneCommune = $this->normalizeLocation($zone->commune);
            $zoneCity = $this->normalizeLocation($zone->city);

            if ($zoneCommune !== $destinationCommune) {
                return false;
            }

            return $city === '' || $zoneCity === '' || $zoneCity === $city;
        });

        if ($communeZones->isEmpty()) {
            $communeZones = $zones->filter(function ($zone) use ($city) {
                if (($zone->coverage_type ?? null) !== 'city') {
                    return false;
                }

                if ($this->normalizeLocation($zone->commune) !== 'all') {
                    return false;
                }

                $zoneCity = $this->normalizeLocation($zone->city);

                return $city !== '' && ($zoneCity === '' || $zoneCity === $city);
            });
        }

        $supportsVehicleCode = Schema::hasColumn('seller_delivery_zones', 'vehicle_code');
        $genericAbidjanCoverage = false;

        // Si le GPS confirme Abidjan sans identifier précisément la commune,
        // une boutique vendeur reste éligible uniquement si sa grille couvre
        // effectivement toutes les communes d'Abidjan pour ce véhicule.
        if ($communeZones->isEmpty()
            && $this->normalizeLocation($request?->input('delivery_zone')) === 'abidjan'
            && $this->locations->isGenericAbidjan($destinationCommune)) {
            $expected = collect(config('client_space.abidjan_communes', []))
                ->map(fn ($name) => $this->normalizeLocation($name))
                ->filter()
                ->unique()
                ->values();

            $coverageCandidates = $zones->filter(function ($zone) use ($supportsVehicleCode, $vehicleCode) {
                if (! $supportsVehicleCode) {
                    return true;
                }

                return ! filled($zone->vehicle_code) || (string) $zone->vehicle_code === $vehicleCode;
            });

            $covered = $coverageCandidates
                ->map(fn ($zone) => $this->normalizeLocation($zone->commune))
                ->filter()
                ->unique()
                ->values();

            if ($expected->diff($covered)->isEmpty()) {
                $communeZones = $coverageCandidates
                    ->filter(fn ($zone) => $expected->contains($this->normalizeLocation($zone->commune)));
                $genericAbidjanCoverage = true;
            }
        }

        if ($communeZones->isEmpty()) {
            return null;
        }

        $eligible = $communeZones->filter(function ($zone) use ($metrics) {
            if ($zone->max_weight_kg !== null && $metrics['weight_kg'] > (float) $zone->max_weight_kg) {
                return false;
            }

            if ($zone->max_volume_m3 !== null && $metrics['volume_m3'] > (float) $zone->max_volume_m3) {
                return false;
            }

            return true;
        });

        if ($eligible->isEmpty()) {
            return null;
        }

        if ($supportsVehicleCode) {
            $exact = $eligible
                ->filter(fn ($zone) => (string) $zone->vehicle_code === $vehicleCode)
                ->sortByDesc(fn ($zone) => $genericAbidjanCoverage ? (float) $zone->delivery_price : 0)
                ->first();

            if ($exact) {
                return $exact;
            }

            // Compatibilité avec les anciennes lignes « tarif unique par commune ».
            $generic = $eligible
                ->filter(fn ($zone) => ! filled($zone->vehicle_code))
                ->sortBy(fn ($zone) => $genericAbidjanCoverage ? -(float) $zone->delivery_price : (float) $zone->delivery_price)
                ->first();

            if ($generic) {
                return $generic;
            }

            return null;
        }

        return $genericAbidjanCoverage
            ? $eligible->sortByDesc('delivery_price')->first()
            : $eligible->sortBy('delivery_price')->first();
    }

    private function normalizeLocation(mixed $value): string
    {
        return $this->locations->communeSlug(is_scalar($value) ? (string) $value : null);
    }


    private function invalidLogisticsDataReason(Collection $items): ?string
    {
        foreach ($items as $item) {
            $product = $item->product ?? null;

            if (! $product) {
                return 'Produit introuvable dans le panier.';
            }

            $weight = (float) ($product->weight_kg ?? $product->weight ?? 0);

            if ($weight <= 0) {
                return 'Le poids logistique du produit « ' . ($product->name ?? 'Produit') . ' » est manquant. Il doit etre corrige avant le calcul de livraison.';
            }
        }

        return null;
    }

    private function metrics(Collection $items): array
    {
        return [
            'weight_kg' => $this->calculator->weightKg($items),
            'volume_m3' => $this->calculator->volumeM3($items),
        ];
    }

    private function requiredTrips(float $total, mixed $capacity): int
    {
        if (! is_numeric($capacity) || (float) $capacity <= 0 || $total <= (float) $capacity) {
            return 1;
        }

        return (int) ceil($total / (float) $capacity);
    }

    private function validCoordinates(mixed $lat, mixed $lng): bool
    {
        return is_numeric($lat)
            && is_numeric($lng)
            && (float) $lat >= -90
            && (float) $lat <= 90
            && (float) $lng >= -180
            && (float) $lng <= 180
            && (abs((float) $lat) > 0 || abs((float) $lng) > 0);
    }

    private function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $distance = round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);

        return $distance > 0 ? $distance : 0.01;
    }

    private function delayText(?int $durationMinutes): string
    {
        if (! $durationMinutes) {
            return 'Delai estime apres validation';
        }

        if ($durationMinutes <= 120) {
            return 'Environ ' . max(1, $durationMinutes) . ' min apres validation';
        }

        return 'Sous ' . max(1, (int) ceil($durationMinutes / 60)) . 'h apres validation';
    }


}
