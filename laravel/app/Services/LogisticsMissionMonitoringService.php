<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryIncident;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Supervision automatique des missions OVANIE Logistics.
 *
 * Cette couche ne remplace pas le signalement terrain du livreur. Elle crée
 * uniquement une alerte réelle lorsqu'une ETA enregistrée est dépassée et
 * qu'aucun incident de retard ouvert n'existe déjà pour la mission.
 */
class LogisticsMissionMonitoringService
{
    public function run(): array
    {
        $created = 0;
        $checked = 0;

        $assignments = DeliveryAssignment::query()
            ->with(['orderItem.order', 'orderItem.shipment', 'driver'])
            ->whereIn('status', ['accepted', 'collecting', 'picked_up', 'in_transit', 'arrived'])
            ->where(function ($query) {
                $query->whereNotNull('manual_eta_at')
                    ->orWhereNotNull('estimated_delivery_at');
            })
            ->get()
            ->groupBy(fn (DeliveryAssignment $assignment) => $assignment->resolved_mission_number);

        foreach ($assignments as $missionNumber => $rows) {
            $latest = $rows->sortByDesc('id')->unique('order_item_id')->values();
            $eta = $latest->pluck('manual_eta_at')->filter()->sort()->first()
                ?: $latest->pluck('estimated_delivery_at')->filter()->sort()->first();

            if (! $eta || ! $eta->isPast()) {
                continue;
            }

            $checked++;
            $itemIds = $latest->pluck('order_item_id')->filter()->unique()->values();
            $item = OrderItem::query()->with(['order', 'shipment'])->whereIn('id', $itemIds)->first();
            if (! $item) {
                continue;
            }

            $alreadyOpen = DeliveryIncident::query()
                ->operationalReal()
                ->whereIn('order_item_id', $itemIds)
                ->whereIn('incident_type', ['retard_important', 'retard_livraison'])
                ->whereNotIn('status', ['resolved', 'closed'])
                ->exists();

            if ($alreadyOpen) {
                continue;
            }

            $minutesLate = max(1, (int) $eta->diffInMinutes(now()));
            $driver = $latest->pluck('driver')->filter()->first();

            DeliveryIncident::create([
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'shipment_id' => $item->shipment_id,
                'reported_by_type' => 'system_alert',
                'reported_by_id' => null,
                'incident_type' => $minutesLate >= 60 ? 'retard_important' : 'retard_livraison',
                'severity' => $minutesLate >= 120 ? 'high' : 'medium',
                'responsibility' => 'transport',
                'description' => "ETA dépassée de {$minutesLate} minute(s) pour la mission {$missionNumber}.",
                'latitude' => $driver?->latitude,
                'longitude' => $driver?->longitude,
                'occurred_at' => now(),
                'status' => 'open',
                'next_action' => 'Vérifier la progression réelle de la mission et informer le client si le retard est confirmé.',
                'meta' => [
                    'signal_source' => 'system_alert',
                    'signal_source_label' => 'Alerte automatique OVANIE Logistics',
                    'mission_number' => $missionNumber,
                    'impact_level' => 'delay',
                    'impact_label' => 'Retard probable',
                    'delivery_interrupted' => false,
                    'eta_at' => $eta->toIso8601String(),
                    'delay_minutes' => $minutesLate,
                    'customer_notification_required' => true,
                    'driver_name' => $driver?->name,
                    'driver_phone' => $driver?->phone,
                ],
            ]);

            $created++;
        }

        return ['checked' => $checked, 'created' => $created];
    }
}
