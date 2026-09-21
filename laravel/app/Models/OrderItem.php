<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'original_product_id',
        'fulfilled_product_id',
        'original_shop_id',
        'fulfilled_shop_id',
        'optimization_applied',
        'optimization_savings',
        'optimization_meta',
        'shop_id',
        'shipment_id',
        'quantity',
        'price',
        'subtotal',
        'vendor_visible_at',
        'logistics_vehicle_code',
        'logistics_vehicle_label',
        'logistics_weight_kg',
        'logistics_volume_m3',

        // Workflow vendeur par ligne de commande
        'vendor_status',
        'vendor_status_note',
        'vendor_status_updated_at',
        'vendor_confirmed_at',
        'vendor_prepared_at',
        'vendor_shipped_at',
        'vendor_delivered_at',
        'vendor_cancelled_at',
        'vendor_cancel_reason',

        // Expédition vendeur par ligne de commande
        'vendor_carrier',
        'vendor_tracking_number',
        'vendor_shipment_date',

        // Livraison prise en charge
        'vendor_delivery_status',
        'vendor_delivery_updated_at',
        'vendor_delivery_note',

        // Chauffeur / camion
        'driver_name',
        'driver_phone',
        'vehicle_plate',

        'driver_latitude',
        'driver_longitude',
        'driver_location_updated_at',
        'seller_tracking_session_id',

        // Photos
        'pickup_photo',
        'delivery_photo',

        // OTP livraison
        'delivery_otp_code',
        'delivery_otp_verified_at',

        // Workflow central livraison client / vendeur / logistique
        'delivery_provider',
        'delivery_mode',
        'delivery_status',
        'delivery_delay',
        'delivery_price',
        'delivery_service_id',
        'delivery_zone_id',
        'seller_ready_for_pickup_at',
        'logistics_assigned_at',
        'picked_up_at',
        'delivery_completed_at',
        'delivery_failed_at',
        'delivery_failure_reason',
        'reception_status',
        'reception_confirmed_at',
        'payout_status',
        'payout_ready_at',
        'return_status',
        'is_paid',

        // Commission existante si déjà utilisée dans ton projet
        'commission_status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'vendor_visible_at' => 'datetime',
        'logistics_weight_kg' => 'float',
        'logistics_volume_m3' => 'float',
        'vendor_status_updated_at' => 'datetime',
        'vendor_confirmed_at' => 'datetime',
        'vendor_prepared_at' => 'datetime',
        'vendor_shipped_at' => 'datetime',
        'vendor_delivered_at' => 'datetime',
        'vendor_cancelled_at' => 'datetime',
        'vendor_shipment_date' => 'date',
        'vendor_delivery_updated_at' => 'datetime',
        'driver_location_updated_at' => 'datetime',
        'delivery_otp_verified_at' => 'datetime',
        'delivery_price' => 'decimal:2',
        'seller_ready_for_pickup_at' => 'datetime',
        'logistics_assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivery_completed_at' => 'datetime',
        'delivery_failed_at' => 'datetime',
        'reception_confirmed_at' => 'datetime',
        'payout_ready_at' => 'datetime',
        'is_paid' => 'boolean',
        'optimization_applied' => 'boolean',
        'optimization_savings' => 'float',
        'optimization_meta' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function originalProduct()
    {
        return $this->belongsTo(Product::class, 'original_product_id');
    }

    public function fulfilledProduct()
    {
        return $this->belongsTo(Product::class, 'fulfilled_product_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }



    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function receptionItem()
    {
        return $this->hasOne(OrderReceptionFormItem::class, 'order_item_id');
    }

    public function sellerTrackingSession()
    {
        return $this->belongsTo(SellerDeliveryTrackingSession::class, 'seller_tracking_session_id');
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

    public function latestDeliveryAssignment()
    {
        return $this->hasOne(DeliveryAssignment::class)->latestOfMany();
    }

    public function getDeliveryProviderLabelAttribute(): string
    {
        return app(\App\Services\OrderWorkflowService::class)->providerLabel($this->delivery_provider);
    }

    public function getDeliveryStatusLabelAttribute(): string
    {
        return app(\App\Services\OrderWorkflowService::class)->deliveryStatusLabel($this->delivery_status ?: $this->vendor_delivery_status);
    }

    public function getIsOvanieDeliveryAttribute(): bool
    {
        return $this->delivery_provider === \App\Services\OrderWorkflowService::PROVIDER_OVANIE;
    }

    public function getIsSellerDeliveryAttribute(): bool
    {
        return $this->delivery_provider === \App\Services\OrderWorkflowService::PROVIDER_SELLER;
    }

    public function getIsPayoutReadyAttribute(): bool
    {
        return $this->delivery_status === \App\Services\OrderWorkflowService::DELIVERY_DELIVERED
            && $this->reception_status === 'confirmed';
    }

    public function calculateSubtotal(): float
    {
        $this->subtotal = (float) $this->price * (int) $this->quantity;

        return (float) $this->subtotal;
    }

    public function markVendorStatus(string $status, ?string $note = null): void
    {
        $this->vendor_status = $status;
        $this->vendor_status_note = $note;
        $this->vendor_status_updated_at = now();

        match ($status) {
            'accepted' => $this->vendor_confirmed_at = $this->vendor_confirmed_at ?: now(),
            'preparing', 'ready' => $this->vendor_prepared_at = $this->vendor_prepared_at ?: now(),
            'shipped' => $this->vendor_shipped_at = $this->vendor_shipped_at ?: now(),
            'delivered' => $this->vendor_delivered_at = $this->vendor_delivered_at ?: now(),
            'cancelled' => $this->vendor_cancelled_at = $this->vendor_cancelled_at ?: now(),
            default => null,
        };

        $this->save();
    }

    public function isVendorDelivered(): bool
    {
        return $this->vendor_status === 'delivered'
            || $this->vendor_delivery_status === 'delivered'
            || $this->delivery_status === \App\Services\OrderWorkflowService::DELIVERY_DELIVERED
            || $this->delivery_otp_verified_at !== null;
    }
}
