<?php

namespace App\Services\SupportAi;

use App\Models\DeliveryIncident;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\Shop;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportEntityLinker
{
    public function __construct(private readonly SupportIdentityResolver $identities) {}

    public function link(
        SupportConversation $conversation,
        string $text = '',
        array $hints = [],
        bool $trusted = false,
        ?User $authenticatedUser = null,
    ): SupportConversation {
        $conversation = $this->identities->applyToConversation(
            $conversation,
            $authenticatedUser,
            $hints['requester_email'] ?? null,
            $hints['requester_phone'] ?? null,
        );

        $requester = $conversation->requester;
        $references = $this->extractReferences($text, $hints);

        $order = $this->resolveOrder($conversation, $requester, $references, $trusted, $text);
        $payment = $this->resolvePayment($conversation, $requester, $references, $order, $trusted, $text);
        $shipment = $this->resolveShipment($conversation, $requester, $references, $order, $trusted, $text);

        if (! $order && $payment?->order) {
            $order = $payment->order;
        }
        if (! $order && $shipment?->order) {
            $order = $shipment->order;
        }

        if ($order && $payment && (int) $payment->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'payment_reference' => 'Le paiement indiqué n’appartient pas à la commande liée.',
            ]);
        }

        if ($order && $shipment && (int) $shipment->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'tracking_reference' => 'La livraison indiquée n’appartient pas à la commande liée.',
            ]);
        }

        $incident = $this->resolveIncident($conversation, $order, $shipment);
        $shop = $this->resolveShop($conversation, $requester, $order, $trusted);

        $changes = [
            'order_id' => $order?->id ?: $conversation->order_id,
            'payment_id' => $payment?->id ?: $conversation->payment_id,
            'shipment_id' => $shipment?->id ?: $conversation->shipment_id,
            'shop_id' => $shop?->id ?: $conversation->shop_id,
            'delivery_incident_id' => $incident?->id ?: $conversation->delivery_incident_id,
            'linked_at' => now(),
        ];

        $conversation->forceFill($changes)->save();

        return $conversation->fresh([
            'requester', 'order', 'payment', 'shipment', 'shop', 'deliveryIncident',
        ]);
    }

    private function resolveOrder(
        SupportConversation $conversation,
        ?User $requester,
        array $references,
        bool $trusted,
        string $text,
    ): ?Order {
        if ($conversation->order_id) {
            return $conversation->order;
        }

        $order = null;
        if (! empty($references['order_id'])) {
            $order = Order::find($references['order_id']);
        }
        if (! $order && ! empty($references['order_reference'])) {
            $order = Order::query()
                ->where('order_number', $references['order_reference'])
                ->orWhere('invoice_number', $references['order_reference'])
                ->orWhere('tracking_number', $references['order_reference'])
                ->first();
        }

        if ($order && ($trusted || $this->canAccessOrder($requester, $order))) {
            return $order;
        }

        if (! $requester || ! $this->mentionsAny($text, ['commande', 'achat', 'facture', 'livraison', 'paiement', 'colis', 'suivi'])) {
            return null;
        }

        $query = Order::query()->operational()->where(function ($builder) use ($requester) {
            $builder->where('client_id', $requester->id);
            $shopId = $requester->shop?->id;
            if ($shopId) {
                $builder->orWhere('shop_id', $shopId)
                    ->orWhereHas('items', fn ($items) => $items->where('shop_id', $shopId));
            }
        })->latest('id')->limit(2)->get();

        return $query->count() === 1 ? $query->first() : null;
    }

    private function resolvePayment(
        SupportConversation $conversation,
        ?User $requester,
        array $references,
        ?Order $order,
        bool $trusted,
        string $text,
    ): ?Payment {
        if ($conversation->payment_id) {
            return $conversation->payment;
        }

        $payment = null;
        if (! empty($references['payment_id'])) {
            $payment = Payment::find($references['payment_id']);
        }
        if (! $payment && ! empty($references['payment_reference'])) {
            $payment = Payment::query()
                ->where('reference', $references['payment_reference'])
                ->orWhere('transaction_id', $references['payment_reference'])
                ->first();
        }

        if ($payment && ($trusted || $this->canAccessPayment($requester, $payment))) {
            return $payment;
        }

        if (! $order || ! $this->mentionsAny($text, ['paiement', 'transaction', 'debite', 'débit', 'remboursement', 'payé', 'paye'])) {
            return null;
        }

        $payments = $order->payments()->latest('id')->limit(2)->get();

        return $payments->count() === 1 ? $payments->first() : null;
    }

    private function resolveShipment(
        SupportConversation $conversation,
        ?User $requester,
        array $references,
        ?Order $order,
        bool $trusted,
        string $text,
    ): ?Shipment {
        if ($conversation->shipment_id) {
            return $conversation->shipment;
        }

        $shipment = null;
        if (! empty($references['shipment_id'])) {
            $shipment = Shipment::find($references['shipment_id']);
        }
        if (! $shipment && ! empty($references['tracking_reference'])) {
            $shipment = Shipment::query()->where('tracking_number', $references['tracking_reference'])->first();
        }

        if ($shipment && ($trusted || $this->canAccessShipment($requester, $shipment))) {
            return $shipment;
        }

        if (! $order || ! $this->mentionsAny($text, ['livraison', 'livreur', 'colis', 'expedition', 'expédition', 'suivi', 'retard', 'chantier'])) {
            return null;
        }

        $shipments = $order->shipments()
            ->whereNotIn('status', ['cancelled'])
            ->latest('id')
            ->limit(2)
            ->get();

        return $shipments->count() === 1 ? $shipments->first() : null;
    }

    private function resolveIncident(SupportConversation $conversation, ?Order $order, ?Shipment $shipment): ?DeliveryIncident
    {
        if ($conversation->delivery_incident_id) {
            return $conversation->deliveryIncident;
        }

        if (! $order && ! $shipment) {
            return null;
        }

        return DeliveryIncident::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->when($shipment, fn ($query) => $query->where('shipment_id', $shipment->id))
            ->when(! $shipment && $order, fn ($query) => $query->where('order_id', $order->id))
            ->latest('occurred_at')
            ->first();
    }

    private function resolveShop(SupportConversation $conversation, ?User $requester, ?Order $order, bool $trusted): ?Shop
    {
        if ($conversation->shop_id) {
            return $conversation->shop;
        }

        if ($order?->shop_id) {
            $shop = $order->shop;
            if ($shop && ($trusted || $this->canAccessShop($requester, $shop) || $order->client_id === $requester?->id)) {
                return $shop;
            }
        }

        return $requester?->shop;
    }

    private function extractReferences(string $text, array $hints): array
    {
        $references = [
            'order_id' => $hints['order_id'] ?? null,
            'payment_id' => $hints['payment_id'] ?? null,
            'shipment_id' => $hints['shipment_id'] ?? null,
            'order_reference' => $hints['order_reference'] ?? null,
            'payment_reference' => $hints['payment_reference'] ?? null,
            'tracking_reference' => $hints['tracking_reference'] ?? null,
        ];

        if (! $references['order_reference'] && preg_match('/\b(?:CMD|ORD|OV|COM)-[A-Z0-9-]{4,}\b/i', $text, $match)) {
            $references['order_reference'] = $match[0];
        }
        if (! $references['payment_reference'] && preg_match('/\b(?:PAY|TXN|TRX)-[A-Z0-9-]{4,}\b/i', $text, $match)) {
            $references['payment_reference'] = $match[0];
        }
        if (! $references['tracking_reference'] && preg_match('/\b(?:TRK|SHIP|EXP)-[A-Z0-9-]{4,}\b/i', $text, $match)) {
            $references['tracking_reference'] = $match[0];
        }

        return $references;
    }

    private function canAccessOrder(?User $user, Order $order): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) $order->client_id === (int) $user->id) {
            return true;
        }

        $shopId = $user->shop?->id;
        return $shopId && ((int) $order->shop_id === (int) $shopId || $order->items()->where('shop_id', $shopId)->exists());
    }

    private function canAccessPayment(?User $user, Payment $payment): bool
    {
        if (! $user) {
            return false;
        }

        return (int) $payment->user_id === (int) $user->id
            || ($payment->order && $this->canAccessOrder($user, $payment->order));
    }

    private function canAccessShipment(?User $user, Shipment $shipment): bool
    {
        return $shipment->order ? $this->canAccessOrder($user, $shipment->order) : false;
    }

    private function canAccessShop(?User $user, Shop $shop): bool
    {
        return $user && (int) $shop->user_id === (int) $user->id;
    }

    private function mentionsAny(string $text, array $needles): bool
    {
        $normalized = Str::lower(Str::ascii($text));

        foreach ($needles as $needle) {
            if (Str::contains($normalized, Str::lower(Str::ascii($needle)))) {
                return true;
            }
        }

        return false;
    }
}
