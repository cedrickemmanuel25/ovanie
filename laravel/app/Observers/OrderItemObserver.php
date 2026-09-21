<?php

namespace App\Observers;

use App\Models\OrderItem;
use App\Services\VendorPayoutScheduleService;

class OrderItemObserver
{
    public function updated(OrderItem $item): void
    {
        if (! $item->wasChanged([
            'delivery_status',
            'delivery_completed_at',
            'reception_status',
            'reception_confirmed_at',
            'payout_status',
            'payout_ready_at',
            'return_status',
        ])) {
            return;
        }

        $order = $item->order()->first();

        if ($order) {
            app(VendorPayoutScheduleService::class)->syncOrder($order);
        }
    }
}
