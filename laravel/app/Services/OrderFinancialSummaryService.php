<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderFinancialSummaryService
{
    public function __construct(private readonly OrderSettlementService $settlements)
    {
    }

    public function summarize(Order $order): array
    {
        $order->loadMissing(['items', 'payments']);

        $subtotal = $order->subtotal !== null
            ? (float) $order->subtotal
            : (float) $order->items->sum(fn ($item) => $item->subtotal !== null
                ? (float) $item->subtotal
                : ((float) $item->price * (int) $item->quantity));

        $delivery = $order->delivery_fee_total !== null
            ? (float) $order->delivery_fee_total
            : ($order->delivery_fee !== null
                ? (float) $order->delivery_fee
                : (float) $order->items->sum('delivery_price'));

        $discount = max(0, (float) ($order->discount ?? 0));
        $loyaltyDiscount = max(0, (float) ($order->loyalty_discount ?? 0));
        $calculatedTotal = max(0, round($subtotal + $delivery - $discount - $loyaltyDiscount, 2));
        $storedTotal = $order->total_amount !== null ? (float) $order->total_amount : $calculatedTotal;
        $difference = round($storedTotal - $calculatedTotal, 2);
        $isConsistent = abs($difference) <= 0.01;

        if (! $isConsistent) {
            Log::warning('Incohérence financière détectée sur une commande OVANIE.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'stored_total' => $storedTotal,
                'calculated_total' => $calculatedTotal,
                'difference' => $difference,
            ]);
        }

        $settledAmount = $this->settlements->settledAmount($order);
        $initialFeeAmount = $this->settlements->initialFeeAmount($order);
        $refundedAmount = $this->settlements->refundedAmount($order);
        $outstandingAmount = round(max(0, $storedTotal - $settledAmount), 2);
        $overpaidAmount = round(max(0, $settledAmount - $storedTotal), 2);
        $netSettledAmount = round(max(0, $settledAmount - $refundedAmount), 2);
        $grossCollectedAmount = round($settledAmount + $initialFeeAmount, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'delivery' => round($delivery, 2),
            'discount' => round($discount, 2),
            'loyalty_discount' => round($loyaltyDiscount, 2),
            'calculated_total' => $calculatedTotal,
            'stored_total' => round($storedTotal, 2),
            // Le montant contractuel enregistré au checkout reste la source de vérité.
            'display_total' => round($storedTotal, 2),
            // Paiements qui diminuent réellement le principal de la commande.
            'settled_amount' => round($settledAmount, 2),
            // Frais initiaux séparés du solde de la commande (ex. paiement à la livraison).
            'initial_fee_amount' => round($initialFeeAmount, 2),
            'gross_collected_amount' => $grossCollectedAmount,
            'refunded_amount' => round($refundedAmount, 2),
            'net_settled_amount' => $netSettledAmount,
            'outstanding_amount' => $outstandingAmount,
            'overpaid_amount' => $overpaidAmount,
            'difference' => $difference,
            'is_consistent' => $isConsistent,
        ];
    }
}
