<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;

class OrderSettlementService
{
    /**
     * Paiements qui règlent réellement le principal de la commande.
     *
     * La commission/frais initial du paiement à la livraison n'est jamais
     * déduit du solde marchand : il s'agit d'un encaissement distinct.
     */
    private const CUSTOMER_SETTLEMENT_TYPES = [
        'order_payment',
        'order_line_payment',
        'order_lines_payment',
        'item_payment',
        'bank_transfer_payment',
        'cod_collection',
        'cod_delivery_group',
        'delivery_group_payment',
    ];

    private const INITIAL_FEE_TYPES = [
        'commission_payment',
        'platform_fee',
        'service_fee',
    ];

    private const SETTLED_STATUSES = [
        Payment::STATUS_PAID,
        Payment::STATUS_ESCROW_HELD,
        Payment::STATUS_RELEASED_TO_VENDOR,
    ];

    public function settledAmount(Order $order): float
    {
        $amount = Payment::query()
            ->where('order_id', $order->id)
            ->whereIn('status', self::SETTLED_STATUSES)
            ->where(function ($query) {
                // Compatibilité avec les anciens paiements complets sans type.
                $query->whereNull('type')
                    ->orWhereIn('type', self::CUSTOMER_SETTLEMENT_TYPES);
            })
            ->sum('amount');

        return round(max(0, (float) $amount), 2);
    }

    public function initialFeeAmount(Order $order): float
    {
        $amount = Payment::query()
            ->where('order_id', $order->id)
            ->whereIn('status', self::SETTLED_STATUSES)
            ->whereIn('type', self::INITIAL_FEE_TYPES)
            ->sum('amount');

        return round(max(0, (float) $amount), 2);
    }

    public function refundedAmount(Order $order): float
    {
        $amount = Payment::query()
            ->where('order_id', $order->id)
            ->where('type', 'return_refund')
            ->where('status', Payment::STATUS_REFUNDED)
            ->sum('amount');

        return round(max(0, (float) $amount), 2);
    }

    public function outstandingAmount(Order $order): float
    {
        $orderTotal = max(0, (float) ($order->total_amount ?? 0));

        return round(max(0, $orderTotal - $this->settledAmount($order)), 2);
    }

    public function amountForLine(Order $order, float $lineAmount): float
    {
        return round(min(max(0, $lineAmount), $this->outstandingAmount($order)), 2);
    }
}
