<?php

namespace App\Services\Geo;

use App\Models\DeliveryDriver;
use App\Models\Shipment;
use App\Models\Shop;
use App\Services\SellerDeliverySupervisionService;
use App\Support\LogisticsOperationalDataScope;
use Illuminate\Support\Facades\Schema;

class MapDataService
{
    public function __construct(
        private readonly WazeLinkService $waze,
        private readonly CoordinateSanitizer $coordinates,
    )
    {
    }

    public function logisticsMapData(): array
    {
        return [
            'map' => $this->mapboxConfig(),
            'shops' => $this->shopsForMap(),
            'shipments' => $this->shipmentsForMap(),
            'drivers' => $this->driversForMap(),
            'seller_deliveries' => app(SellerDeliverySupervisionService::class)->trackingRows()->values()->all(),
            'routes' => $this->routesForMap(),
        ];
    }

    public function shopsForMap(): array
    {
        return LogisticsOperationalDataScope::shops(Shop::query())
            ->where(function ($mode) {
                $mode->where('logistics_type', 'ovanie')
                    ->orWhereNull('logistics_type')
                    ->orWhere('logistics_type', '');
            })
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->filter(fn (Shop $shop) => $this->valid($shop->latitude, $shop->longitude))
            ->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'address' => collect([$shop->address, $shop->landmark, $shop->district, $shop->commune, $shop->city])->filter()->implode(' - '),
                'latitude' => (float) $shop->latitude,
                'longitude' => (float) $shop->longitude,
                // Alias utilisés par les vues Mapbox existantes.
                'lat' => (float) $shop->latitude,
                'lng' => (float) $shop->longitude,
                'logistics_type' => 'ovanie',
                'geo_status' => $shop->geo_status ?: Shop::GEO_STATUS_VERIFICATION_REQUIRED,
                'geo_status_label' => $shop->geo_status_label,
                'geo_status_severity' => $shop->geo_status_severity,
                'geo_precision' => $shop->geo_precision,
                'geo_precision_score' => $shop->geo_precision_score,
                'geo_source' => $shop->geo_source,
                'geo_verified_at' => optional($shop->geo_verified_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function shipmentsForMap(): array
    {
        if (! Schema::hasTable('shipments')) {
            return [];
        }

        return LogisticsOperationalDataScope::shipments(Shipment::with(['order.client', 'shop', 'orderItem']))
            ->where('provider_type', 'ovanie')
            ->whereHas('order', fn ($order) => $order->whereIn('payment_status', ['paid', 'commission_paid']))
            ->whereNotIn('status', ['delivered', 'cancelled', 'completed'])
            ->whereNotNull('pickup_latitude')
            ->whereNotNull('pickup_longitude')
            ->whereNotNull('delivery_latitude')
            ->whereNotNull('delivery_longitude')
            ->latest()
            ->get()
            ->filter(fn (Shipment $shipment) => $this->valid($shipment->pickup_latitude, $shipment->pickup_longitude)
                && $this->valid($shipment->delivery_latitude, $shipment->delivery_longitude))
            ->map(function (Shipment $shipment) {
                $wazeUrl = $shipment->waze_url;

                if (! $wazeUrl && $this->valid($shipment->delivery_latitude, $shipment->delivery_longitude)) {
                    $wazeUrl = $this->waze->navigationUrl((float) $shipment->delivery_latitude, (float) $shipment->delivery_longitude);
                }

                return [
                    'id' => $shipment->id,
                    'order_id' => $shipment->order_id,
                    'shop_id' => $shipment->shop_id,
                    'client_name' => $shipment->order?->client?->name,
                    'status' => $shipment->status,
                    'pickup_latitude' => $shipment->pickup_latitude,
                    'pickup_longitude' => $shipment->pickup_longitude,
                    'delivery_latitude' => $shipment->delivery_latitude,
                    'delivery_longitude' => $shipment->delivery_longitude,
                    'route' => [
                        'distance_km' => $shipment->distance_km,
                        'duration_minutes' => $shipment->duration_minutes,
                        'traffic_delay_minutes' => $shipment->traffic_delay_minutes,
                        'geometry' => $this->geometry($shipment->route_geometry),
                    ],
                    'vehicle' => [
                        'vehicle_code' => $shipment->vehicle_code ?: $shipment->orderItem?->logistics_vehicle_code,
                        'vehicle_label' => $shipment->vehicle_label ?: $shipment->orderItem?->logistics_vehicle_label,
                        'total_weight_kg' => $shipment->total_weight_kg ?: $shipment->orderItem?->logistics_weight_kg,
                        'total_volume_m3' => $shipment->total_volume_m3 ?: $shipment->orderItem?->logistics_volume_m3,
                    ],
                    'navigation' => [
                        'waze_url' => $wazeUrl,
                    ],
                    'internal_carrier' => [
                        'type' => $shipment->internal_carrier_type,
                        'name' => $shipment->internal_carrier_name,
                    ],
                    'distance_km' => $shipment->distance_km,
                    'duration_minutes' => $shipment->duration_minutes,
                    'traffic_delay_minutes' => $shipment->traffic_delay_minutes,
                    'route_geometry' => $shipment->route_geometry,
                ];
            })
            ->values()
            ->all();
    }

    public function driversForMap(): array
    {
        return LogisticsOperationalDataScope::drivers(DeliveryDriver::query())
            ->gpsOnline()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->filter(fn (DeliveryDriver $driver) => $this->valid($driver->latitude, $driver->longitude))
            ->map(fn (DeliveryDriver $driver) => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'latitude' => $driver->latitude,
                'longitude' => $driver->longitude,
                'last_seen_at' => optional($driver->last_seen_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function routesForMap(): array
    {
        return collect($this->shipmentsForMap())
            ->filter(fn ($shipment) => filled($shipment['route']['geometry'] ?? null))
            ->map(fn ($shipment) => [
                'shipment_id' => $shipment['id'],
                'geometry' => $shipment['route']['geometry'],
            ])
            ->values()
            ->all();
    }

    private function mapboxConfig(): array
    {
        return [
            'provider' => 'mapbox',
            'token' => config('geo.mapbox.public_token'),
            'style' => config('geo.mapbox.style_url', 'mapbox://styles/mapbox/streets-v12'),
            'center' => [
                'latitude' => config('geo.default_lat', 5.359952),
                'longitude' => config('geo.default_lng', -4.008256),
            ],
            'zoom' => config('geo.default_zoom', 12),
        ];
    }

    private function geometry($geometry): ?array
    {
        if (is_array($geometry)) {
            return $geometry;
        }

        if (! is_string($geometry) || trim($geometry) === '') {
            return null;
        }

        $decoded = json_decode($geometry, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function valid($lat, $lng): bool
    {
        return $this->coordinates->normalize($lat, $lng) !== null;
    }
}
