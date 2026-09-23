<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportRequester extends Model
{
    protected $fillable = [
        'requester_type', 'user_id', 'delivery_driver_id', 'shop_id',
        'name', 'email', 'phone', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function driver() { return $this->belongsTo(DeliveryDriver::class, 'delivery_driver_id'); }
    public function shop() { return $this->belongsTo(Shop::class); }
    public function tickets() { return $this->hasMany(SupportTicket::class); }
    public function conversations() { return $this->hasMany(SupportConversation::class); }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->requester_type) {
            'client' => 'Client',
            'vendor' => 'Vendeur',
            'commercial' => 'Commercial',
            'driver' => 'Livreur partenaire',
            default => 'Visiteur',
        };
    }
}
