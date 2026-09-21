<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'client_id',
        'gift_card_id',
        'customer_name',
        'vendor_id',
        'shop_id',
        'product_id',
        'quantity',
        'invoice_number',
        'status',
        'commission_amount',
        'payment_method',
        'payment_status',
        'subtotal',
        'delivery_fee',
        'delivery_fee_total',
        'ovanie_delivery_fee',
        'seller_delivery_fee',
        'partner_delivery_fee',
        'delivery_pricing_status',
        'delivery_pricing_meta',
        'delivery_breakdown',
        'selected_carriers',
        'total_amount',
        'bank_reference',
        'receipt_path',
        'payment_proof',
        'carrier',
        'tracking_number',
        'shipment_date',
        'notes',
        'discount',
        'loyalty_points_used',
        'loyalty_discount',
        'gift_card_amount',
        'phone',
        'address',
        'delivery_address',
        'delivery_zone',
        'delivery_destination_type',
        'delivery_site_name',
        'delivery_recipient_name',
        'delivery_recipient_phone',
        'delivery_commune',
        'delivery_quartier',
        'delivery_city',
        'delivery_lat',
        'delivery_lng',
        'delivery_latitude',
        'delivery_longitude',
        'delivery_geo_accuracy',
        'delivery_geo_source',
        'delivery_started_at',
        'delivery_min_date',
        'delivery_max_date',
        'delivery_note',
        'delivery_provider',
        'delivery_status',
        'seller_ready_for_pickup_at',
        'driver_assigned_at',
        'picked_up_at',
        'in_transit_at',
        'delivered_at',
        'reception_status',
        'payout_status',
        'platform_commission',
        'vendor_payout_amount',
    ];

    protected $casts = [
        'delivery_breakdown' => 'array',
        'delivery_pricing_meta' => 'array',
        'selected_carriers' => 'array',
        'delivery_started_at' => 'date',
        'delivery_min_date' => 'date',
        'delivery_max_date' => 'date',
        'delivery_latitude' => 'float',
        'delivery_longitude' => 'float',
        'loyalty_points_used' => 'integer',
        'loyalty_discount' => 'decimal:2',
        'gift_card_amount' => 'decimal:2',
        'delivery_geo_accuracy' => 'float',
        'seller_ready_for_pickup_at' => 'datetime',
        'driver_assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];


    public function deliverySelections()
    {
        return $this->hasMany(OrderDeliverySelection::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function giftCard()
    {
        return $this->belongsTo(GiftCard::class, 'gift_card_id');
    }


    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function returns()
    {
        return $this->hasMany(ReturnModel::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function deliveryProofs()
    {
        return $this->hasMany(DeliveryProof::class);
    }

    public function deliveryAssignments()
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function getDeliveryProviderLabelAttribute(): string
    {
        $providers = $this->items->pluck('delivery_provider')->filter()->unique()->values();

        if ($providers->count() === 1) {
            return app(\App\Services\OrderWorkflowService::class)->providerLabel($providers->first());
        }

        if ($providers->isEmpty()) {
            return 'Mode de livraison à définir';
        }

        return 'Livraison mixte';
    }

    public function vendorItemsForShop(int $shopId)
    {
        return $this->items()->where('shop_id', $shopId);
    }

    /**
     * Commandes réellement validées pour les espaces client et administration.
     * Les tentatives de paiement en ligne non confirmées restent des brouillons
     * techniques et ne doivent pas être présentées comme des commandes passées.
     */
    public function scopeOperational($query)
    {
        return $query->where(function ($orderQuery) {
            $orderQuery
                ->where('payment_method', '!=', 'paydunya')
                ->orWhereNull('payment_method')
                ->orWhereIn('payment_status', [
                    'paid',
                    'commission_paid',
                    'partial',
                    'escrow_held',
                    'verified',
                ]);
        });
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopePaymentDone($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeHasShipment($query)
    {
        return $query->whereNotNull('shipment_date');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped' && ! is_null($this->shipment_date);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function calculateCommission(float $rate): float
    {
        $this->commission_amount = ((float) $this->total_amount * $rate) / 100;

        return (float) $this->commission_amount;
    }

    /**
     * Recalcule le statut global sans permettre à un vendeur de modifier toute la commande directement.
     * Statuts globaux conservés pour compatibilité avec ta table existante :
     * pending, paid, shipped, completed, cancelled.
     */
    public function refreshGlobalStatusFromItems(): void
    {
        $items = $this->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        $total = $items->count();

        $cancelled = $items->where('vendor_status', 'cancelled')->count();
        $delivered = $items->filter(function ($item) {
            return $item->vendor_status === 'delivered'
                || $item->vendor_delivery_status === 'delivered'
                || $item->delivery_status === \App\Services\OrderWorkflowService::DELIVERY_DELIVERED
                || $item->delivery_otp_verified_at !== null;
        })->count();
        $inProgress = $items->filter(function ($item) {
            return in_array($item->vendor_status, ['accepted', 'preparing', 'ready', 'shipped', 'delivered'], true)
                || in_array($item->vendor_delivery_status, ['in_delivery', 'delivered'], true)
                || in_array($item->delivery_status, [\App\Services\OrderWorkflowService::DELIVERY_READY_FOR_PICKUP, \App\Services\OrderWorkflowService::DELIVERY_ASSIGNED, \App\Services\OrderWorkflowService::DELIVERY_PICKED_UP, \App\Services\OrderWorkflowService::DELIVERY_IN_TRANSIT, \App\Services\OrderWorkflowService::DELIVERY_DELIVERED], true);
        })->count();

        if ($cancelled === $total) {
            $this->status = 'cancelled';
        } elseif ($delivered === $total) {
            $this->status = 'completed';
        } elseif ($items->contains(fn ($item) => $item->vendor_status === 'shipped' || $item->vendor_delivery_status === 'in_delivery' || in_array($item->delivery_status, [\App\Services\OrderWorkflowService::DELIVERY_PICKED_UP, \App\Services\OrderWorkflowService::DELIVERY_IN_TRANSIT], true))) {
            $this->status = 'shipped';
        } elseif (in_array($this->payment_status, ['paid', 'escrow_held'], true) && $inProgress > 0) {
            $this->status = 'paid';
        } elseif (in_array($this->payment_status, ['paid', 'escrow_held'], true)) {
            $this->status = 'paid';
        } elseif (in_array($this->payment_status, ['commission_paid', 'partial'], true)) {
            // Les frais initiaux ou un paiement partiel autorisent la poursuite du
            // parcours, sans jamais présenter la commande comme intégralement payée.
            $this->status = 'confirmed';
        } else {
            $this->status = 'pending';
        }

        $this->save();
    }
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function supportConversations()
    {
        return $this->hasMany(SupportConversation::class);
    }

}
