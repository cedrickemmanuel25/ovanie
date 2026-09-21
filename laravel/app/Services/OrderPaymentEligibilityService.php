<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnModel;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderPaymentEligibilityService
{
    public function __construct(private readonly OrderSettlementService $settlements)
    {
    }

    public function canPayOrderBalance(Order $order): bool
    {
        try {
            $this->assertOrderBalancePayable($order);
            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function canPayLine(Order $order, OrderItem $item): bool
    {
        try {
            $this->assertLinePayable($order, $item);
            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function assertOrderBalancePayable(Order $order): void
    {
        $this->assertBaseOrderEligibility($order);
        $order->loadMissing('items');

        $unpaidItems = $order->items->filter(fn (OrderItem $item) => ! (bool) $item->is_paid);

        if ($unpaidItems->isEmpty()) {
            throw ValidationException::withMessages([
                'payment' => 'Cette commande est déjà entièrement réglée.',
            ]);
        }

        $this->assertItemsDelivered($unpaidItems);
        $this->assertNoBlockingReturn($order, $unpaidItems);
    }

    public function assertLinePayable(Order $order, OrderItem $item): void
    {
        $this->assertBaseOrderEligibility($order);

        if ((int) $item->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'payment' => 'Le produit sélectionné n’appartient pas à cette commande.',
            ]);
        }

        if ((bool) $item->is_paid) {
            throw ValidationException::withMessages([
                'payment' => 'Ce produit est déjà payé.',
            ]);
        }

        $this->assertItemsDelivered(collect([$item]));
        $this->assertNoBlockingReturn($order, collect([$item]));
    }

    private function assertBaseOrderEligibility(Order $order): void
    {
        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'payment' => 'Une commande annulée ne peut plus recevoir de paiement client.',
            ]);
        }

        if (PaymentService::normalizeMethod($order->payment_method) !== PaymentService::METHOD_CASH_ON_DELIVERY) {
            throw ValidationException::withMessages([
                'payment' => 'Le paiement du solde après livraison est réservé aux commandes en paiement à la livraison.',
            ]);
        }

        if ($this->settlements->outstandingAmount($order) <= 0.01) {
            throw ValidationException::withMessages([
                'payment' => 'Cette commande ne présente aucun solde restant.',
            ]);
        }
    }

    private function assertItemsDelivered(Collection $items): void
    {
        $allDelivered = $items->every(fn (OrderItem $item) => $item->isVendorDelivered());

        if (! $allDelivered) {
            throw ValidationException::withMessages([
                'payment' => 'Le solde ne peut être payé qu’après la livraison effective des articles concernés.',
            ]);
        }
    }

    private function assertNoBlockingReturn(Order $order, Collection $items): void
    {
        $itemIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();

        $hasBlockingReturn = ReturnModel::query()
            ->where('order_id', $order->id)
            ->whereIn('order_item_id', $itemIds)
            ->whereIn('return_type', ['return', 'refund', 'claim'])
            ->whereNotIn('status', [
                ReturnModel::STATUS_REJECTED,
                ReturnModel::STATUS_CLOSED,
                'cancelled',
                'resolved',
            ])
            ->exists();

        if ($hasBlockingReturn) {
            throw ValidationException::withMessages([
                'payment' => 'Le paiement est suspendu pendant le traitement d’un retour, d’une réclamation ou d’un remboursement actif sur les articles concernés.',
            ]);
        }
    }
}
