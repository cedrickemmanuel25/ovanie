<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retire uniquement les coordonnées approximatives injectées par l'ancienne
     * migration 2026_06_29_030000. Les coordonnées GPS, carte ou carnet
     * d'adresses possédant une provenance réelle ne sont jamais touchées.
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')
            || ! Schema::hasColumn('orders', 'delivery_lat')
            || ! Schema::hasColumn('orders', 'delivery_lng')) {
            return;
        }

        $placeholders = [
            ['commune' => 'Cocody', 'lat' => 5.3717000, 'lng' => -3.9883000],
            ['commune' => 'Plateau', 'lat' => 5.3230000, 'lng' => -4.0210000],
            ['commune' => 'Marcory', 'lat' => 5.3027000, 'lng' => -3.9827000],
            ['commune' => 'Treichville', 'lat' => 5.2935000, 'lng' => -4.0140000],
            ['commune' => 'Yopougon', 'lat' => 5.3369000, 'lng' => -4.0891000],
            ['commune' => 'Abobo', 'lat' => 5.4353000, 'lng' => -4.0247000],
            ['commune' => 'Adjamé', 'lat' => 5.3650000, 'lng' => -4.0236000],
            ['commune' => 'Koumassi', 'lat' => 5.3006000, 'lng' => -3.9457000],
            ['commune' => 'Port-Bouët', 'lat' => 5.2524000, 'lng' => -3.9299000],
            ['commune' => 'Bingerville', 'lat' => 5.3558000, 'lng' => -3.8854000],
        ];

        foreach ($placeholders as $placeholder) {
            $orderIds = DB::table('orders')
                ->where('delivery_commune', $placeholder['commune'])
                ->where('delivery_lat', $placeholder['lat'])
                ->where('delivery_lng', $placeholder['lng'])
                ->when(
                    Schema::hasColumn('orders', 'delivery_latitude'),
                    fn ($query) => $query->whereNull('delivery_latitude')
                )
                ->when(
                    Schema::hasColumn('orders', 'delivery_longitude'),
                    fn ($query) => $query->whereNull('delivery_longitude')
                )
                ->when(
                    Schema::hasColumn('orders', 'delivery_geo_source'),
                    fn ($query) => $query->where(function ($sourceQuery) {
                        $sourceQuery->whereNull('delivery_geo_source')
                            ->orWhere('delivery_geo_source', '');
                    })
                )
                ->pluck('id');

            if ($orderIds->isEmpty()) {
                continue;
            }

            if (Schema::hasTable('shipments')
                && Schema::hasColumn('shipments', 'order_id')
                && Schema::hasColumn('shipments', 'delivery_latitude')
                && Schema::hasColumn('shipments', 'delivery_longitude')) {
                $shipmentUpdate = [
                    'delivery_latitude' => null,
                    'delivery_longitude' => null,
                ];

                foreach (['distance_km', 'duration_minutes', 'routing_provider', 'traffic_delay_minutes', 'no_traffic_duration_minutes', 'routed_at', 'route_geometry', 'waze_url'] as $column) {
                    if (Schema::hasColumn('shipments', $column)) {
                        $shipmentUpdate[$column] = null;
                    }
                }

                DB::table('shipments')
                    ->whereIn('order_id', $orderIds)
                    ->where('delivery_latitude', $placeholder['lat'])
                    ->where('delivery_longitude', $placeholder['lng'])
                    ->update($shipmentUpdate);
            }

            $orderUpdate = [
                'delivery_lat' => null,
                'delivery_lng' => null,
            ];

            if (Schema::hasColumn('orders', 'delivery_geo_source')) {
                $orderUpdate['delivery_geo_source'] = 'legacy_commune_placeholder_removed';
            }

            DB::table('orders')
                ->whereIn('id', $orderIds)
                ->update($orderUpdate);
        }
    }

    public function down(): void
    {
        // Volontairement non réversible : réinjecter des centres de commune
        // ferait réapparaître des positions qui ne sont pas celles des clients.
    }
};
