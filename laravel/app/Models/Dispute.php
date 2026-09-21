<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dispute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id', 'order_item_id', 'client_id', 'vendor_id', 'shop_id',
        'order_reference', 'client_name', 'reason', 'response', 'internal_notes',
        'status', 'responded_at', 'escalated', 'escalated_at',
    ];

    protected $casts = [
        'escalated' => 'boolean',
        'responded_at' => 'datetime',
        'escalated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function client() { return $this->belongsTo(User::class, 'client_id'); }
    public function vendor() { return $this->belongsTo(User::class, 'vendor_id'); }
    public function shop() { return $this->belongsTo(Shop::class); }
    public function supportTickets() { return $this->hasMany(SupportTicket::class); }
    public function supportConversations() { return $this->hasMany(SupportConversation::class, 'dispute_id'); }
}
