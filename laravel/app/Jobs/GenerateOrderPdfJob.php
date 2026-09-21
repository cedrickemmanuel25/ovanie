<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateOrderPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::query()
            ->with(['items.product', 'client', 'user', 'shop'])
            ->find($this->orderId);

        if (!$order) {
            return;
        }

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            Log::warning('DomPDF non installé : PDF commande non généré', ['order_id' => $this->orderId]);
            return;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.order', ['order' => $order]);
        Storage::put("orders/order-{$order->id}.pdf", $pdf->output());
    }
}
