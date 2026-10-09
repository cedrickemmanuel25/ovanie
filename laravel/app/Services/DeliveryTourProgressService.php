<?php

namespace App\Services;

use App\Models\DeliveryTour;
use App\Models\DeliveryTourStop;
use App\Models\OrderItem;

/**
 * Fait suivre automatiquement les tournées planifiées par la Logistique :
 * étapes cochées dès l'enlèvement / la livraison réels, tournée « En cours »
 * puis « Terminée » sans action manuelle.
 */
class DeliveryTourProgressService
{
    private const PICKED = [
        OrderWorkflowService::DELIVERY_PICKED_UP,
        OrderWorkflowService::DELIVERY_IN_TRANSIT,
        OrderWorkflowService::DELIVERY_DELIVERED,
    ];

    public function syncForItem(OrderItem $item): void
    {
        $tourIds = DeliveryTourStop::query()
            ->where('order_item_id', $item->id)
            ->pluck('delivery_tour_id')
            ->unique();

        foreach ($tourIds as $tourId) {
            $tour = DeliveryTour::query()->with('stops')->find($tourId);
            if (! $tour || in_array($tour->status, ['done', 'cancelled'], true)) {
                continue;
            }
            $this->syncTour($tour);
        }
    }

    public function syncTour(DeliveryTour $tour): void
    {
        $items = OrderItem::query()
            ->whereIn('id', $tour->stops->pluck('order_item_id')->filter()->unique())
            ->get(['id', 'delivery_status'])
            ->keyBy('id');

        foreach ($tour->stops as $stop) {
            $status = $items->get($stop->order_item_id)?->delivery_status;
            $done = $stop->stop_type === 'pickup'
                ? in_array($status, self::PICKED, true)
                : $status === OrderWorkflowService::DELIVERY_DELIVERED;
            if ($done && $stop->status !== 'completed') {
                $stop->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            }
        }

        $stops = $tour->stops()->get();
        $completed = $stops->where('status', 'completed')->count();
        if ($stops->isNotEmpty() && $completed === $stops->count()) {
            $tour->forceFill(['status' => 'done', 'started_at' => $tour->started_at ?: now(), 'completed_at' => now()])->save();
        } elseif ($completed > 0 && $tour->status === 'planned') {
            $tour->forceFill(['status' => 'in_progress', 'started_at' => $tour->started_at ?: now()])->save();
        }
    }
}
