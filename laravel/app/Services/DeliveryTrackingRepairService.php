<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DriverLocation;
use App\Models\SellerDeliveryTrackingSession;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeliveryTrackingRepairService
{
    /**
     * Répare uniquement les liaisons de suivi existantes. Aucune commande ni
     * position n'est inventée et aucune donnée métier n'est supprimée.
     *
     * @return array<string,int>
     */
    public function run(bool $apply = false): array
    {
        $report = [
            'assignments_without_mission' => 0,
            'locations_without_assignment' => 0,
            'locations_without_order' => 0,
            'shipments_status_mismatch' => 0,
            'seller_sessions_position_mismatch' => 0,
            'updated' => 0,
        ];

        if (Schema::hasTable('delivery_assignments')) {
            DeliveryAssignment::query()
                ->whereNull('mission_number')
                ->orWhere('mission_number', '')
                ->orderBy('id')
                ->chunkById(300, function ($assignments) use (&$report, $apply) {
                    foreach ($assignments as $assignment) {
                        $report['assignments_without_mission']++;
                        if (! $apply) {
                            continue;
                        }

                        $mission = (string) (data_get($assignment->meta, 'mission_number')
                            ?: sprintf('OVL-%05d-%02d', (int) $assignment->order_id, 1));
                        $assignment->forceFill(['mission_number' => $mission])->save();
                        $report['updated']++;
                    }
                });
        }

        if (Schema::hasTable('driver_locations')) {
            DriverLocation::query()->with('shipment')->orderBy('id')->chunkById(300, function ($locations) use (&$report, $apply) {
                foreach ($locations as $location) {
                    $shipment = $location->shipment;
                    $orderId = $location->order_id ?: $shipment?->order_id;
                    $assignment = null;

                    if (Schema::hasColumn('driver_locations', 'delivery_assignment_id') && ! $location->delivery_assignment_id) {
                        $assignment = DeliveryAssignment::query()
                            ->when($shipment?->order_item_id, fn ($query, $itemId) => $query->where('order_item_id', $itemId))
                            ->when(! $shipment?->order_item_id && $orderId, fn ($query) => $query->where('order_id', $orderId))
                            ->where('driver_id', $location->driver_id)
                            ->latest('id')
                            ->first();

                        $report['locations_without_assignment']++;
                    }

                    if (Schema::hasColumn('driver_locations', 'order_id') && ! $location->order_id && $orderId) {
                        $report['locations_without_order']++;
                    }

                    if (! $apply) {
                        continue;
                    }

                    $updates = [];
                    if ($assignment && Schema::hasColumn('driver_locations', 'delivery_assignment_id')) {
                        $updates['delivery_assignment_id'] = $assignment->id;
                    }
                    if (Schema::hasColumn('driver_locations', 'mission_number') && blank($location->mission_number)) {
                        $updates['mission_number'] = $assignment?->resolved_mission_number;
                    }
                    if (Schema::hasColumn('driver_locations', 'order_id') && ! $location->order_id && $orderId) {
                        $updates['order_id'] = $orderId;
                    }

                    if ($updates) {
                        $location->forceFill($updates)->save();
                        $report['updated']++;
                    }
                }
            });
        }

        if (Schema::hasTable('shipments')) {
            Shipment::query()->with('orderItem')->orderBy('id')->chunkById(300, function ($shipments) use (&$report, $apply) {
                foreach ($shipments as $shipment) {
                    $itemStatus = $shipment->orderItem?->delivery_status;
                    if (! $itemStatus || $shipment->status === $itemStatus) {
                        continue;
                    }

                    $report['shipments_status_mismatch']++;
                    if ($apply) {
                        $shipment->forceFill(['status' => $itemStatus])->save();
                        $report['updated']++;
                    }
                }
            });
        }

        if (Schema::hasTable('seller_delivery_tracking_sessions') && Schema::hasTable('seller_driver_locations')) {
            SellerDeliveryTrackingSession::query()->with('latestLocation')->orderBy('id')->chunkById(200, function ($sessions) use (&$report, $apply) {
                foreach ($sessions as $session) {
                    $latest = $session->latestLocation;
                    if (! $latest) {
                        continue;
                    }

                    $different = ! $session->last_location_at
                        || (float) $session->last_latitude !== (float) $latest->latitude
                        || (float) $session->last_longitude !== (float) $latest->longitude;

                    if (! $different) {
                        continue;
                    }

                    $report['seller_sessions_position_mismatch']++;
                    if ($apply) {
                        $session->forceFill([
                            'last_location_at' => $latest->recorded_at,
                            'last_latitude' => $latest->latitude,
                            'last_longitude' => $latest->longitude,
                            'last_accuracy' => $latest->accuracy,
                            'last_speed' => $latest->speed,
                            'last_heading' => $latest->heading,
                        ])->save();
                        $report['updated']++;
                    }
                }
            });
        }

        return $report;
    }
}
