<?php

namespace App\Services;

use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OvanieShipmentConsolidationService
{
    private const VEHICLES = [
        ['code' => 'moto', 'label' => 'Moto', 'weight' => 20.0, 'volume' => 0.18],
        ['code' => 'tricycle', 'label' => 'Tricycle', 'weight' => 250.0, 'volume' => 1.50],
        ['code' => 'pickup', 'label' => 'Pickup', 'weight' => 1000.0, 'volume' => 7.00],
        ['code' => 'camion_3t', 'label' => 'Camion 3 tonnes', 'weight' => 3000.0, 'volume' => 20.00],
        ['code' => 'camion_10t', 'label' => 'Camion 10 tonnes', 'weight' => 10000.0, 'volume' => 60.00],
    ];

    public static function vehicleOptions(): array
    {
        return self::VEHICLES;
    }

    public function groups(Collection $items): Collection
    {
        return $items
            ->filter(fn (OrderItem $item) => $item->order_id !== null)
            ->groupBy(fn (OrderItem $item) => $this->baseKey($item))
            ->flatMap(fn (Collection $orderItems) => $this->splitIntoVehicleLoads($orderItems))
            ->sortByDesc(fn (array $group) => $group['updated_at']?->timestamp ?? 0)
            ->values();
    }

    public function groupForItem(OrderItem $item): array
    {
        $query = OrderItem::with([
            'order.client',
            'product.shop',
            'latestDeliveryAssignment.driver',
            'latestDeliveryAssignment.latestLocation',
            'deliveryAssignments.driver',
            'shipment',
        ])->where('order_id', $item->order_id);

        if (Schema::hasColumn('order_items', 'delivery_provider')) {
            $query->where('delivery_provider', OrderWorkflowService::PROVIDER_OVANIE);
        } else {
            $query->whereHas('product.shop', fn ($shop) => $shop->where('logistics_type', 'ovanie'));
        }

        $groups = $this->groups($query->get());

        return $groups->first(fn (array $group) => $group['items']->contains('id', $item->id))
            ?? $this->makeGroup(collect([$item->loadMissing('order.client', 'product.shop')]), 1);
    }

    public function counts(Collection $groups): array
    {
        $count = fn (string ...$phases) => $groups->filter(
            fn (array $group) => in_array((string) ($group['operational_phase'] ?? 'to_offer'), $phases, true)
        )->count();

        $toOffer = $count('to_offer');
        $waitingAcceptance = $count('waiting_acceptance');
        $acceptedWaitingVendor = $count('accepted_waiting_vendor');
        $readyForPickup = $count('ready_for_pickup');
        $collecting = $count('collecting');
        $inDelivery = $count('in_delivery');
        $delivered = $count('delivered');
        $incident = $count('incident');

        return [
            'all' => $groups->count(),
            'to_offer' => $toOffer,
            'waiting_acceptance' => $waitingAcceptance,
            'accepted_waiting_vendor' => $acceptedWaitingVendor,
            'ready_for_pickup' => $readyForPickup,
            'collecting' => $collecting,
            'in_delivery' => $inDelivery,
            'delivered' => $delivered,
            'incident' => $incident,

            // Compatibilité avec les anciens widgets encore présents dans
            // d'autres écrans logistiques. L'espace Missions utilise désormais
            // les clés métier ci-dessus et ne parle plus d'affectation manuelle.
            'prepare' => $toOffer + $waitingAcceptance + $acceptedWaitingVendor,
            'to_assign' => $toOffer + $waitingAcceptance,
            'ship' => $acceptedWaitingVendor + $readyForPickup + $collecting,
            'route' => $collecting + $inDelivery,
            'done' => $delivered,
        ];
    }

    public function filter(
        Collection $groups,
        ?string $search,
        ?string $zone,
        ?string $status,
        ?string $vehicle = null,
        ?int $driverId = null
    ): Collection {
        $groups = $groups->filter(function (array $group) use ($search, $zone, $vehicle, $driverId) {
            if ($search) {
                $needle = Str::lower(trim($search));
                $haystack = Str::lower(collect([
                    $group['mission_number'],
                    $group['order']?->order_number,
                    $group['client_name'],
                    $group['address'],
                    $group['driver_name'],
                    $group['vehicle_label'],
                    $group['shops']->pluck('name')->implode(' '),
                    $group['items']->pluck('product.name')->implode(' '),
                ])->filter()->implode(' '));

                if (! Str::contains($haystack, $needle)) {
                    return false;
                }
            }

            if ($zone) {
                $zoneNeedle = Str::lower(trim($zone));
                $zoneHaystack = Str::lower(collect([
                    $group['order']?->delivery_commune,
                    $group['order']?->delivery_zone,
                    $group['order']?->delivery_city,
                    $group['address'],
                ])->filter()->implode(' '));

                if (! Str::contains($zoneHaystack, $zoneNeedle)) {
                    return false;
                }
            }

            if ($vehicle && $group['vehicle_code'] !== $vehicle) {
                return false;
            }

            if ($driverId && (int) ($group['driver_id'] ?? 0) !== $driverId) {
                return false;
            }

            return true;
        });

        return $groups->filter(function (array $group) use ($status) {
            if (! $status || $status === 'all') {
                return true;
            }

            $phase = (string) ($group['operational_phase'] ?? 'to_offer');

            return match ($status) {
                'to_offer' => $phase === 'to_offer',
                'waiting_acceptance' => $phase === 'waiting_acceptance',
                'accepted_waiting_vendor' => $phase === 'accepted_waiting_vendor',
                'ready_for_pickup' => $phase === 'ready_for_pickup',
                'collecting' => $phase === 'collecting',
                'in_delivery' => $phase === 'in_delivery',
                'delivered' => $phase === 'delivered',
                'incident' => $phase === 'incident',

                // Anciennes URL conservées pour ne pas casser les favoris.
                'to_assign' => in_array($phase, ['to_offer', 'waiting_acceptance'], true),
                'prepare' => in_array($phase, ['to_offer', 'waiting_acceptance', 'accepted_waiting_vendor'], true),
                'assigned' => in_array($phase, ['accepted_waiting_vendor', 'ready_for_pickup'], true),
                default => true,
            };
        })->values();
    }

    private function splitIntoVehicleLoads(Collection $items): Collection
    {
        $items = $items
            ->sortByDesc(fn (OrderItem $item) => max(
                $this->weight($item) / self::VEHICLES[array_key_last(self::VEHICLES)]['weight'],
                $this->volume($item) / self::VEHICLES[array_key_last(self::VEHICLES)]['volume']
            ))
            ->values();

        $maxVehicle = self::VEHICLES[array_key_last(self::VEHICLES)];
        $totalWeight = $items->sum(fn (OrderItem $item) => $this->weight($item));
        $totalVolume = $items->sum(fn (OrderItem $item) => $this->volume($item));

        if ($totalWeight <= $maxVehicle['weight'] && $totalVolume <= $maxVehicle['volume']) {
            return collect([$this->makeGroup($items, 1)]);
        }

        $bins = [];

        foreach ($items as $item) {
            $itemWeight = $this->weight($item);
            $itemVolume = $this->volume($item);
            $placed = false;

            foreach ($bins as &$bin) {
                if (($bin['weight'] + $itemWeight) <= $maxVehicle['weight']
                    && ($bin['volume'] + $itemVolume) <= $maxVehicle['volume']) {
                    $bin['items']->push($item);
                    $bin['weight'] += $itemWeight;
                    $bin['volume'] += $itemVolume;
                    $placed = true;
                    break;
                }
            }
            unset($bin);

            if (! $placed) {
                $bins[] = [
                    'items' => collect([$item]),
                    'weight' => $itemWeight,
                    'volume' => $itemVolume,
                ];
            }
        }

        return collect($bins)
            ->values()
            ->map(fn (array $bin, int $index) => $this->makeGroup($bin['items'], $index + 1));
    }

    private function makeGroup(Collection $items, int $sequence): array
    {
        $items = $items->sortBy('id')->values();
        /** @var OrderItem $representative */
        $representative = $items->first();
        $order = $representative?->order;
        $weight = round($items->sum(fn (OrderItem $item) => $this->weight($item)), 3);
        $volume = round($items->sum(fn (OrderItem $item) => $this->volume($item)), 4);
        $vehicle = $this->vehicleFor($weight, $volume);
        $statuses = $items->map(fn (OrderItem $item) => $item->delivery_status ?: 'pending');
        $readyCount = $statuses->filter(fn (string $status) => in_array($status, [
            'ready_for_pickup', 'assigned', 'picked_up', 'in_transit', 'delivered',
        ], true))->count();

        $shops = $items
            ->map(fn (OrderItem $item) => $item->product?->shop)
            ->filter()
            ->unique('id')
            ->values();

        $pickupStops = $items
            ->groupBy(fn (OrderItem $item) => $item->product?->shop?->id ?: 'unknown')
            ->map(function (Collection $shopItems) {
                $shop = $shopItems->first()?->product?->shop;

                return [
                    'id' => $shop?->id ?: 'unknown',
                    'shop' => $shop,
                    'items' => $shopItems->values(),
                    'address' => collect([
                        $shop?->address,
                        $shop?->landmark,
                        $shop?->district,
                        $shop?->commune,
                        $shop?->city,
                    ])->filter()->unique()->implode(' - ') ?: 'Adresse de collecte à confirmer',
                    'latitude' => $shop?->latitude,
                    'longitude' => $shop?->longitude,
                    'weight' => round($shopItems->sum(fn (OrderItem $item) => $this->weight($item)), 3),
                    'volume' => round($shopItems->sum(fn (OrderItem $item) => $this->volume($item)), 4),
                    'ready' => $shopItems->every(fn (OrderItem $item) => in_array($item->delivery_status, [
                        'ready_for_pickup', 'assigned', 'picked_up', 'in_transit', 'delivered',
                    ], true)),
                ];
            })
            ->values();

        $allAssignments = $items
            ->flatMap(function (OrderItem $item) {
                if ($item->relationLoaded('deliveryAssignments')) {
                    return $item->deliveryAssignments;
                }

                return $item->deliveryAssignments()->with('driver')->get();
            })
            ->filter()
            ->values();

        $winnerStatuses = ['accepted', 'assigned', 'collecting', 'picked_up', 'in_transit', 'arrived', 'delivered'];
        $winnerAssignments = $allAssignments
            ->filter(fn ($assignment) => in_array((string) $assignment->status, $winnerStatuses, true))
            ->sortByDesc(fn ($assignment) => optional($assignment->accepted_at ?: $assignment->updated_at)->timestamp ?? 0)
            ->values();
        $winnerAssignment = $winnerAssignments->first();
        $liveOfferCount = $allAssignments->where('status', 'offered')->count();
        $offeredDriverCount = $allAssignments->where('status', 'offered')->pluck('driver_id')->filter()->unique()->count();

        $deliveryStatus = $this->aggregateStatus($statuses);
        $preparationPercent = $items->count() > 0 ? (int) round(($readyCount / $items->count()) * 100) : 0;
        $readyPickupCount = $pickupStops->filter(fn (array $stop) => (bool) ($stop['ready'] ?? false))->count();
        $assignmentStatuses = $allAssignments->pluck('status')->map(fn ($status) => (string) $status);

        $operationalPhase = match (true) {
            $deliveryStatus === 'delivery_failed' || $assignmentStatuses->contains('incident') => 'incident',
            $deliveryStatus === 'delivered' || $assignmentStatuses->contains('delivered') => 'delivered',
            $deliveryStatus === 'in_transit' || $assignmentStatuses->contains('in_transit') || $assignmentStatuses->contains('arrived') => 'in_delivery',
            $deliveryStatus === 'picked_up' || $assignmentStatuses->contains('picked_up') || $assignmentStatuses->contains('collecting') => 'collecting',
            $winnerAssignment !== null && $preparationPercent >= 100 => 'ready_for_pickup',
            $winnerAssignment !== null => 'accepted_waiting_vendor',
            $liveOfferCount > 0 => 'waiting_acceptance',
            default => 'to_offer',
        };

        $operationalLabel = match ($operationalPhase) {
            'to_offer' => 'À proposer aux livreurs',
            'waiting_acceptance' => 'En attente d’acceptation',
            'accepted_waiting_vendor' => 'Acceptée · préparation vendeur',
            'ready_for_pickup' => 'Prête pour collecte',
            'collecting' => 'Collecte en cours',
            'in_delivery' => 'En livraison',
            'delivered' => 'Livrée',
            'incident' => 'Incident',
            default => 'En attente',
        };

        $driverItem = $items->first(fn (OrderItem $item) => filled($item->driver_name)
            || filled($item->latestDeliveryAssignment?->driver?->name));
        $winningDriver = $winnerAssignment?->driver;
        $deliveryPriceAmount = round((float) $items->sum(fn (OrderItem $line) => (float) ($line->delivery_price ?? 0)), 0);
        $pricedAssignments = $winnerAssignments->isNotEmpty()
            ? $winnerAssignments
            : $allAssignments->where('status', 'offered')->unique('order_item_id')->values();
        $driverNetAmount = $pricedAssignments->isNotEmpty()
            ? round((float) $pricedAssignments->unique('order_item_id')->sum(fn ($assignment) => (float) ($assignment->driver_net_amount ?? 0)), 0)
            : null;

        $address = collect([
            $order?->delivery_address,
            $order?->address,
            $order?->delivery_quartier,
            $order?->delivery_commune,
            $order?->delivery_city,
        ])->filter()->unique(fn ($value) => Str::lower(trim((string) $value)))->implode(' - ');

        return [
            'key' => $this->baseKey($representative) . ':load:' . $sequence,
            'mission_number' => $representative->latestDeliveryAssignment?->resolved_mission_number
                ?: sprintf('OVL-%05d-%02d', (int) $representative->order_id, $sequence),
            'sequence' => $sequence,
            'representative' => $representative,
            'items' => $items,
            'order' => $order,
            'shops' => $shops,
            'pickup_stops' => $pickupStops,
            'pickup_count' => $pickupStops->count(),
            'article_count' => (int) $items->sum('quantity'),
            'reference_count' => $items->count(),
            'weight' => $weight,
            'volume' => $volume,
            'vehicle_code' => $vehicle['code'],
            'vehicle_label' => $vehicle['label'],
            'status' => $deliveryStatus,
            'operational_phase' => $operationalPhase,
            'operational_label' => $operationalLabel,
            'ready_count' => $readyCount,
            'total_count' => $items->count(),
            'ready_pickup_count' => $readyPickupCount,
            'preparation_percent' => $preparationPercent,
            'live_offer_count' => $liveOfferCount,
            'offered_driver_count' => $offeredDriverCount,
            'assignment_statuses' => $assignmentStatuses->unique()->values()->all(),
            'accepted_at' => $winnerAssignment?->accepted_at,
            'driver_net_amount' => $driverNetAmount,
            'delivery_price_amount' => $deliveryPriceAmount > 0 ? $deliveryPriceAmount : null,
            'driver_id' => $winningDriver?->id ?: $driverItem?->latestDeliveryAssignment?->driver?->id,
            'driver_name' => $winningDriver?->name ?: ($driverItem?->latestDeliveryAssignment?->driver?->name ?: $driverItem?->driver_name),
            'driver_phone' => $winningDriver?->phone ?: ($driverItem?->latestDeliveryAssignment?->driver?->phone ?: $driverItem?->driver_phone),
            'driver_latitude' => $driverItem?->latestDeliveryAssignment?->latestLocation?->latitude,
            'driver_longitude' => $driverItem?->latestDeliveryAssignment?->latestLocation?->longitude,
            'driver_location_at' => optional(
                $driverItem?->latestDeliveryAssignment?->latestLocation?->recorded_at
            )->toIso8601String(),
            'vehicle_plate' => $driverItem?->vehicle_plate,
            'client_name' => $order?->client?->name ?: 'Client',
            'address' => $address ?: 'Adresse à confirmer',
            'updated_at' => $items->max('updated_at'),
        ];
    }

    private function aggregateStatus(Collection $statuses): string
    {
        if ($statuses->isNotEmpty() && $statuses->every(fn ($status) => $status === 'delivered')) {
            return 'delivered';
        }

        if ($statuses->contains('delivery_failed')) {
            return 'delivery_failed';
        }

        if ($statuses->contains('in_transit')) {
            return 'in_transit';
        }

        if ($statuses->contains('picked_up')) {
            return 'picked_up';
        }

        if ($statuses->contains('assigned')) {
            return 'assigned';
        }

        if ($statuses->isNotEmpty() && $statuses->every(fn ($status) => in_array($status, ['ready_for_pickup', 'delivered'], true))) {
            return 'ready_for_pickup';
        }

        return 'pending';
    }

    private function vehicleFor(float $weight, float $volume): array
    {
        foreach (self::VEHICLES as $vehicle) {
            if ($weight <= $vehicle['weight'] && $volume <= $vehicle['volume']) {
                return $vehicle;
            }
        }

        return [
            'code' => 'special',
            'label' => 'Transport spécialisé',
            'weight' => INF,
            'volume' => INF,
        ];
    }

    private function weight(OrderItem $item): float
    {
        if ((float) $item->logistics_weight_kg > 0) {
            return (float) $item->logistics_weight_kg;
        }

        $unitWeight = (float) ($item->product?->weight_kg ?? $item->product?->weight ?? 0);

        return max(0, $unitWeight * max(1, (int) $item->quantity));
    }

    private function volume(OrderItem $item): float
    {
        if ((float) $item->logistics_volume_m3 > 0) {
            return (float) $item->logistics_volume_m3;
        }

        $unitVolume = (float) ($item->product?->volume_m3 ?? 0);

        if ($unitVolume <= 0 && $item->product) {
            $unitVolume = ((float) $item->product->length_cm / 100)
                * ((float) $item->product->width_cm / 100)
                * ((float) $item->product->height_cm / 100);
        }

        return max(0, $unitVolume * max(1, (int) $item->quantity));
    }

    private function baseKey(OrderItem $item): string
    {
        $order = $item->order;
        $destination = Str::lower(trim(collect([
            $order?->delivery_address,
            $order?->delivery_quartier,
            $order?->delivery_commune,
            $order?->delivery_city,
            $order?->delivery_latitude ?? $order?->delivery_lat,
            $order?->delivery_longitude ?? $order?->delivery_lng,
        ])->filter()->implode('|')));

        // Tant qu'aucun livreur n'est affecté, les articles OVANIE destinés à la
        // même adresse sont consolidés en une charge réelle. Dès qu'une mission
        // existe, son numéro devient la frontière du groupe : deux chauffeurs ne
        // peuvent donc jamais être fusionnés sur le suivi client ou logistique.
        $missionNumber = $item->latestDeliveryAssignment?->resolved_mission_number;
        if (filled($missionNumber)) {
            return 'order:' . $item->order_id . ':mission:' . $missionNumber;
        }

        return 'order:' . $item->order_id . ':destination:' . sha1($destination ?: (string) $item->order_id);
    }
}
