<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Services\ClientOrderStatusService;
use App\Services\ClientPaymentPresentationService;
use App\Services\OrderFinancialSummaryService;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ClientOrderResource extends JsonResource
{
    /**
     * Contrat public unique d'une commande client.
     *
     * Le Web et l'application lisent la même commande Laravel. Cette ressource
     * ne crée aucun statut mobile parallèle et n'expose aucune information de
     * boutique, vendeur, commission, reversement ou partenaire logistique.
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $order->loadMissing(['items.product', 'items.variant', 'payments', 'returns']);

        $paymentPresentation = app(ClientPaymentPresentationService::class);
        $financial = app(OrderFinancialSummaryService::class)->summarize($order);
        $statusService = app(ClientOrderStatusService::class);
        $latestPayment = $order->payments->sortByDesc('id')->first();
        $attemptStatus = strtolower(trim((string) ($latestPayment?->status ?? '')));
        $orderPaymentStatus = strtolower(trim((string) ($order->payment_status ?? '')));
        $paymentConfirmed = in_array($orderPaymentStatus, [
            'paid', 'commission_paid', 'escrow_held', 'released_to_vendor',
        ], true) || in_array($attemptStatus, [
            'paid', 'escrow_held', 'released_to_vendor',
        ], true);
        $paymentState = $paymentConfirmed
            ? 'paid'
            : (in_array($attemptStatus, ['failed', 'cancelled'], true) ? $attemptStatus : 'pending');
        $canResumePayment = (string) $order->payment_method === 'paydunya'
            && ! in_array((string) $order->status, ['cancelled', 'canceled'], true)
            && ! $paymentConfirmed
            && (float) ($financial['outstanding_amount'] ?? $financial['display_total'] ?? 0) > 0.01;

        $allDelivered = $order->items->isNotEmpty() && $order->items->every(
            fn (OrderItem $item) => $item->delivery_status === OrderWorkflowService::DELIVERY_DELIVERED
                || $item->isVendorDelivered()
        );
        $allReceptionConfirmed = $order->items->isNotEmpty() && $order->items->every(
            fn (OrderItem $item) => (string) $item->reception_status === 'confirmed'
        );

        return [
            'id' => (int) $order->id,
            'order_number' => $order->order_number,
            'invoice_number' => $order->invoice_number,
            'client_bucket' => $statusService->bucketForOrder($order),
            'status' => $order->status,
            'status_label' => $this->orderStatusLabel($order->status),
            'payment' => [
                'status' => $order->payment_status,
                'status_label' => $this->codAwaitingVerification($order)
                    ? 'Réglé à la livraison · confirmation en cours'
                    : $paymentPresentation->statusLabel($order->payment_status),
                'state' => $paymentState,
                'attempt_status' => $attemptStatus ?: null,
                'attempt_status_label' => $attemptStatus !== ''
                    ? $paymentPresentation->statusLabel($attemptStatus)
                    : null,
                'latest_attempt_id' => $latestPayment?->id ? (int) $latestPayment->id : null,
                'attempt_created_at' => $latestPayment?->created_at?->toIso8601String(),
                'method' => $order->payment_method,
                'method_label' => $paymentPresentation->methodLabel($order->payment_method),
                'settled_amount' => $financial['settled_amount'],
                'initial_fee_amount' => $financial['initial_fee_amount'] ?? 0,
                'refunded_amount' => $financial['refunded_amount'],
                'outstanding_amount' => $financial['outstanding_amount'],
                'overpaid_amount' => $financial['overpaid_amount'] ?? 0,
            ],
            'financial_summary' => [
                'subtotal' => $financial['subtotal'],
                'delivery' => $financial['delivery'],
                'discount' => $financial['discount'],
                'loyalty_discount' => $financial['loyalty_discount'],
                'total' => $financial['display_total'],
            ],
            'delivery' => [
                'status' => $deliveryStatus = ($order->delivery_status ?: app(\App\Services\OrderDeliveryStatusAggregator::class)->aggregate($order->items)),
                'status_label' => $this->deliveryStatusLabel($deliveryStatus, $order->items),
                'destination_type' => $order->delivery_destination_type,
                'address' => $order->delivery_address ?: $order->address,
                'commune' => $order->delivery_commune,
                'quartier' => $order->delivery_quartier,
                'city' => $order->delivery_city,
                'estimated_min_date' => $order->delivery_min_date?->toDateString(),
                'estimated_max_date' => $order->delivery_max_date?->toDateString(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
            ],
            'reception_status' => $order->reception_status,
            'actions' => [
                'can_track' => $statusService->canTrack($order),
                'can_confirm_reception' => $allDelivered && ! $allReceptionConfirmed,
                'reception_confirmed' => $allReceptionConfirmed,
                'can_download_invoice' => filled($order->invoice_number),
                'can_open_return' => in_array((string) $order->status, ['shipped', 'delivered', 'completed'], true),
                'can_cancel' => $statusService->canClientCancel($order),
                'can_reorder' => $statusService->canReorder($order),
                'can_resume_payment' => $canResumePayment,
            ],
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => (int) $item->id,
                'product' => [
                    'id' => $item->product_id ? (int) $item->product_id : null,
                    'name' => $item->product?->name ?: 'Produit indisponible',
                    'slug' => $item->product?->slug,
                    'main_image_url' => $item->variant_image_snapshot ?: $item->variant?->card_url ?: $item->product?->main_image_url,
                ],
                'variant' => $item->product_variant_id ? [
                    'id' => (int) $item->product_variant_id,
                    'label' => $item->variant_label_snapshot ?: $item->variant?->label,
                    'image_url' => $item->variant_image_snapshot ?: $item->variant?->card_url,
                ] : null,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->price,
                'subtotal' => $item->subtotal !== null
                    ? (float) $item->subtotal
                    : round((float) $item->price * (int) $item->quantity, 2),
                'payment_status' => $item->is_paid ? 'paid' : 'pending',
                'delivery_status' => $item->delivery_status,
                'delivery_status_label' => $item->delivery_status_label,
                'reception_status' => $item->reception_status,
                'return_status' => $item->return_status,
            ])->values()->all(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Paiement à la livraison déjà déclaré encaissé par le vendeur : le client a
     * payé, seule la vérification interne OVANIE reste à faire.
     */
    private function codAwaitingVerification($order): bool
    {
        return in_array($order->payment_method, ['cash_on_delivery', 'pay_on_delivery'], true)
            && ! in_array($order->payment_status, ['paid', 'escrow_held', 'released_to_vendor', 'refunded'], true)
            && (in_array($order->status, ['completed', 'delivered'], true)
                || \App\Models\VendorPaymentVerificationRequest::query()
                    ->where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->exists());
    }

    private function orderStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'paid' => 'Paiement confirmé',
            'processing' => 'En préparation',
            'shipped' => 'En livraison',
            'delivered' => 'Livrée',
            'completed' => 'Terminée',
            'cancelled' => 'Annulée',
            'returned' => 'Retournée',
            default => 'Mise à jour en cours',
        };
    }

    private function deliveryStatusLabel(?string $status, ?Collection $items = null): string
    {
        if (in_array($status, [null, '', 'pending'], true)) {
            $vendorStatuses = collect($items)->map(fn ($item) => $item->vendor_status ?: 'pending');

            return $vendorStatuses->isNotEmpty() && $vendorStatuses->every(fn ($s) => $s === 'pending')
                ? 'En attente de confirmation du vendeur'
                : 'Commande confirmée par le vendeur';
        }

        return match ($status) {
            'preparing' => 'Préparation de la commande',
            'ready_for_pickup' => 'Prête pour prise en charge',
            'assigned' => 'Prise en charge planifiée',
            'picked_up' => 'Collectée',
            'in_transit', 'in_delivery' => 'En route',
            'delivered', 'completed' => 'Livrée',
            'late' => 'Livraison retardée',
            'delivery_failed', 'failed', 'problem' => 'Incident de livraison',
            'cancelled' => 'Livraison annulée',
            'returned' => 'Retournée',
            'not_required' => 'Retrait sans livraison',
            default => 'Préparation en cours',
        };
    }
}
