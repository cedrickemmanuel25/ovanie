<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryScheduleService
{
    /**
     * Enregistre une seule planification pour toute la mission consolidée.
     * Cette date devient la source de vérité pour la logistique et le client.
     */
    public function synchronizeMission(
        Collection $items,
        ?int $driverId,
        string $driverName,
        string $driverPhone,
        string $vehiclePlate,
        CarbonInterface $pickupAt,
        CarbonInterface $estimatedDeliveryAt,
        array $missionMeta = []
    ): void {
        DB::transaction(function () use (
            $items,
            $driverId,
            $driverName,
            $driverPhone,
            $vehiclePlate,
            $pickupAt,
            $estimatedDeliveryAt,
            $missionMeta
        ) {
            foreach ($items as $item) {
                /** @var OrderItem $item */
                $item->forceFill([
                    'driver_name' => $driverName,
                    'driver_phone' => $driverPhone,
                    'vehicle_plate' => $vehiclePlate,
                ])->save();

                DeliveryAssignment::updateOrCreate(
                    [
                        'order_item_id' => $item->id,
                        'status' => 'planned',
                    ],
                    [
                        'order_id' => $item->order_id,
                        'driver_id' => $driverId,
                        'assigned_by' => auth()->id(),
                        'pickup_address' => $this->pickupAddress($item),
                        'delivery_address' => $this->deliveryAddress($item),
                        'pickup_scheduled_at' => $pickupAt,
                        'estimated_delivery_at' => $estimatedDeliveryAt,
                        'mission_number' => $missionMeta['mission_number'] ?? null,
                        'gps_status' => 'unknown',
                        'meta' => array_merge($missionMeta, [
                            'estimated_delivery_at' => $estimatedDeliveryAt->toIso8601String(),
                            'planned_before_ready' => true,
                        ]),
                    ]
                );

                $destinationCoordinates = app(\App\Services\Geo\DeliveryCoordinateService::class)
                    ->forOrder($item->order);

                Shipment::updateOrCreate(
                    ['order_item_id' => $item->id],
                    [
                        'order_id' => $item->order_id,
                        'shop_id' => $item->shop_id ?: $item->product?->shop_id,
                        'provider_type' => 'ovanie',
                        'pickup_address' => $this->pickupAddress($item),
                        'pickup_latitude' => $item->product?->shop?->latitude,
                        'pickup_longitude' => $item->product?->shop?->longitude,
                        'delivery_address' => $this->deliveryAddress($item),
                        'delivery_latitude' => $destinationCoordinates['latitude'],
                        'delivery_longitude' => $destinationCoordinates['longitude'],
                        'status' => $item->delivery_status ?: 'pending',
                        'estimated_delivery_at' => $estimatedDeliveryAt,
                        'vehicle_code' => $item->logistics_vehicle_code,
                        'vehicle_label' => $item->logistics_vehicle_label,
                        'total_weight_kg' => $item->logistics_weight_kg,
                        'total_volume_m3' => $item->logistics_volume_m3,
                        'meta' => array_merge((array) ($item->shipment?->meta ?? []), $missionMeta, [
                            'pickup_scheduled_at' => $pickupAt->toIso8601String(),
                            'schedule_source' => 'logistics_planning',
                        ]),
                    ]
                );
            }

            $this->refreshOrderWindow($items->first()?->order);
        });
    }

    public function refreshOrderWindow(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $dates = Shipment::query()
            ->where('order_id', $order->id)
            ->whereNotNull('estimated_delivery_at')
            ->pluck('estimated_delivery_at')
            ->map(fn ($date) => Carbon::parse($date))
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return;
        }

        $order->forceFill([
            'delivery_min_date' => $dates->first()->toDateString(),
            'delivery_max_date' => $dates->last()->toDateString(),
        ])->save();
    }

    public function estimatedFor(Collection $items): ?CarbonInterface
    {
        $value = $items
            ->map(fn (OrderItem $item) => $item->shipment?->estimated_delivery_at)
            ->filter()
            ->sortBy(fn ($date) => Carbon::parse($date)->timestamp)
            ->last();

        return $value ? Carbon::parse($value) : null;
    }

    private function pickupAddress(OrderItem $item): string
    {
        $shop = $item->shop ?: $item->product?->shop;

        return collect([
            $shop?->address,
            $shop?->landmark,
            $shop?->district,
            $shop?->commune,
            $shop?->city,
        ])->filter()->unique()->implode(' - ');
    }

    private function deliveryAddress(OrderItem $item): string
    {
        $order = $item->order;

        return collect([
            $order?->delivery_address,
            $order?->address,
            $order?->delivery_quartier,
            $order?->delivery_commune,
            $order?->delivery_city,
        ])->filter()->unique()->implode(' - ');
    }
}
