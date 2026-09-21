<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnModel extends Model
{

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REFUNDED = 'refunded';

    public const LOGISTICS_PENDING_PICKUP = 'pending_pickup';
    public const LOGISTICS_PICKUP_PLANNED = 'return_pickup_planned';
    public const LOGISTICS_IN_TRANSIT = 'return_in_transit';
    public const LOGISTICS_RECEIVED = 'return_received';
    public const LOGISTICS_REFUND_PENDING = 'refund_pending';
    public const LOGISTICS_REFUNDED = 'refunded';
    public const LOGISTICS_CLAIM_REVIEW = 'claim_review';
    public const LOGISTICS_REFUND_REVIEW = 'refund_review';

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_REJECTED, self::STATUS_CLOSED, self::STATUS_REFUNDED, 'cancelled', 'resolved'], true);
    }


    protected $table = 'returns'; // Nom explicite car 'Return' est un mot réservé en PHP

    protected $fillable = [
        'order_reference',
        'product_name',
        'reason',
        'request_date',
        'status',
        'order_id',
        'order_item_id',
        'client_id',
        'vendor_id',
        'shop_id',
        'product_id',
        'quantity',
        'photo_proof',
        'refund_amount',
        'return_type',
        'logistics_status',
        'vendor_response',
        'accepted_at',
        'rejected_at',
        'refund_prepared_at',
        'refunded_at',
        'resolved_at',
        'meta',
    ];

    protected $casts = [
        'request_date' => 'date',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'refund_prepared_at' => 'datetime',
        'refunded_at' => 'datetime',
        'resolved_at' => 'datetime',
        'refund_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    /**
     * Relation vers la commande liée.
     * Les nouvelles demandes utilisent order_id. Les anciennes données gardent order_reference en affichage.
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'return_id');
    }

    public function supportConversations()
    {
        return $this->hasMany(SupportConversation::class, 'return_id');
    }
}
