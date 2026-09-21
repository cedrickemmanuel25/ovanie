<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\VendorPayoutScheduleService;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged(['payment_status', 'payment_method', 'status'])) {
            return;
        }

        app(VendorPayoutScheduleService::class)->syncOrder($order);
    }
}
