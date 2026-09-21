<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;

class OrderDeliveryStatusAggregator
{
    public function aggregate(Order|Collection $source): string
    {
        $items = $source instanceof Order
            ? $source->items()->get(['delivery_status'])
            : $source;

        $statuses = $items->pluck('delivery_status')->filter()->values();

        if ($statuses->isEmpty()) {
            return OrderWorkflowService::DELIVERY_PENDING;
        }

        if ($statuses->contains(fn ($status) => in_array($status, [
            OrderWorkflowService::DELIVERY_FAILED,
            OrderWorkflowService::DELIVERY_FAILED_STATUS,
            'problem',
        ], true))) {
            return OrderWorkflowService::DELIVERY_FAILED;
        }

        if ($statuses->contains(OrderWorkflowService::DELIVERY_LATE)) {
            return OrderWorkflowService::DELIVERY_LATE;
        }

        if ($statuses->contains(OrderWorkflowService::DELIVERY_IN_TRANSIT)) {
            return OrderWorkflowService::DELIVERY_IN_TRANSIT;
        }

        if ($statuses->contains(OrderWorkflowService::DELIVERY_PICKED_UP)) {
            return OrderWorkflowService::DELIVERY_PICKED_UP;
        }

        if ($statuses->contains(OrderWorkflowService::DELIVERY_ASSIGNED)) {
            return OrderWorkflowService::DELIVERY_ASSIGNED;
        }

        if ($statuses->contains(OrderWorkflowService::DELIVERY_READY_FOR_PICKUP)) {
            return OrderWorkflowService::DELIVERY_READY_FOR_PICKUP;
        }

        if ($statuses->contains(OrderWorkflowService::DELIVERY_PREPARING)) {
            return OrderWorkflowService::DELIVERY_PREPARING;
        }

        $finished = [
            OrderWorkflowService::DELIVERY_DELIVERED,
            OrderWorkflowService::DELIVERY_NOT_REQUIRED,
        ];

        if ($statuses->every(fn ($status) => in_array($status, $finished, true))) {
            return OrderWorkflowService::DELIVERY_DELIVERED;
        }

        return OrderWorkflowService::DELIVERY_PENDING;
    }

    public function sync(Order $order): string
    {
        $status = $this->aggregate($order);
        $payload = ['delivery_status' => $status];

        if ($status === OrderWorkflowService::DELIVERY_DELIVERED) {
            $payload['delivered_at'] = $order->delivered_at ?: now();
        }

        if ($status === OrderWorkflowService::DELIVERY_IN_TRANSIT) {
            $payload['in_transit_at'] = $order->in_transit_at ?: now();
        }

        if ($status === OrderWorkflowService::DELIVERY_PICKED_UP) {
            $payload['picked_up_at'] = $order->picked_up_at ?: now();
        }

        if ($status === OrderWorkflowService::DELIVERY_ASSIGNED) {
            $payload['driver_assigned_at'] = $order->driver_assigned_at ?: now();
        }

        $order->forceFill($payload)->saveQuietly();

        return $status;
    }
}
