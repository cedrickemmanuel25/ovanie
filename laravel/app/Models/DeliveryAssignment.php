<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAssignment extends Model
{
    protected $fillable = [
        'mission_number',
        'order_id',
        'order_item_id',
        'driver_id',
        'vehicle_id',
        'assigned_by',
        'status',
        'gps_status',
        'gps_disabled_reason',
        'gps_last_seen_at',
        'manual_eta_at',
        'last_manual_status_at',
        'pickup_address',
        'delivery_address',
        'pickup_scheduled_at',
        'estimated_delivery_at',
        'accepted_at',
        'started_at',
        'arrived_at',
        'rejected_at',
        'rejection_reason',
        'picked_up_at',
        'delivered_at',
        'meta',
        'price_amount',
        'driver_commission_percent',
        'driver_net_amount',
    ];

    protected $casts = [
        'pickup_scheduled_at' => 'datetime',
        'estimated_delivery_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'arrived_at' => 'datetime',
        'rejected_at' => 'datetime',
        'gps_last_seen_at' => 'datetime',
        'manual_eta_at' => 'datetime',
        'last_manual_status_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'meta' => 'array',
        'price_amount' => 'decimal:2',
        'driver_commission_percent' => 'decimal:2',
        'driver_net_amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryDriver::class, 'driver_id');
    }

    public function locations()
    {
        return $this->hasMany(DriverLocation::class, 'delivery_assignment_id');
    }

    public function latestLocation()
    {
        return $this->hasOne(DriverLocation::class, 'delivery_assignment_id')->latestOfMany('recorded_at');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function getResolvedMissionNumberAttribute(): string
    {
        return (string) ($this->mission_number
            ?: data_get($this->meta, 'mission_number')
            ?: 'OVL-' . str_pad((string) $this->order_id, 5, '0', STR_PAD_LEFT));
    }
}
