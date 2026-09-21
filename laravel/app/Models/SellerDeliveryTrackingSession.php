<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerDeliveryTrackingSession extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INCIDENT = 'incident';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'public_id', 'access_token', 'shop_id', 'order_id', 'status', 'mission_status',
        'gps_status', 'gps_disabled_reason', 'driver_name', 'driver_phone', 'vehicle_plate',
        'started_at', 'accepted_at', 'departed_at', 'arrived_at', 'estimated_delivery_at',
        'manual_eta_at', 'last_manual_status_at', 'ended_at', 'last_location_at',
        'last_latitude', 'last_longitude', 'last_accuracy', 'last_speed', 'last_heading',
        'remaining_distance_km', 'eta_minutes', 'traffic_delay_minutes', 'route_geometry',
        'route_calculated_at', 'incident_type', 'incident_note', 'incident_reported_at',
    ];

    protected $casts = [
        'started_at' => 'datetime', 'accepted_at' => 'datetime', 'departed_at' => 'datetime',
        'arrived_at' => 'datetime', 'estimated_delivery_at' => 'datetime', 'manual_eta_at' => 'datetime',
        'last_manual_status_at' => 'datetime', 'ended_at' => 'datetime', 'last_location_at' => 'datetime',
        'last_latitude' => 'float', 'last_longitude' => 'float', 'last_accuracy' => 'float',
        'last_speed' => 'float', 'last_heading' => 'float', 'remaining_distance_km' => 'float',
        'eta_minutes' => 'integer', 'traffic_delay_minutes' => 'integer',
        'route_calculated_at' => 'datetime', 'incident_reported_at' => 'datetime',
    ];

    public function shop() { return $this->belongsTo(Shop::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function items() { return $this->hasMany(OrderItem::class, 'seller_tracking_session_id'); }
    public function locations() { return $this->hasMany(SellerDriverLocation::class, 'tracking_session_id'); }
    public function latestLocation() { return $this->hasOne(SellerDriverLocation::class, 'tracking_session_id')->latestOfMany('recorded_at'); }

    public function isTrackable(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_INCIDENT], true);
    }

    public function gpsIsAvailable(): bool
    {
        return ! in_array($this->gps_status, ['denied', 'unavailable', 'disabled'], true);
    }
}
