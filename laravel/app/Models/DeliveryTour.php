<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTour extends Model
{
    protected $fillable = [
        'reference',
        'driver_id',
        'vehicle_code',
        'vehicle_label',
        'vehicle_plate',
        'zone_label',
        'status',
        'tour_date',
        'departure_time',
        'started_at',
        'completed_at',
        'optimization_enabled',
        'distance_km',
        'duration_minutes',
        'fleet_vehicle_id',
        'vehicle_capacity_kg',
        'vehicle_volume_m3',
        'routing_provider',
        'route_is_complete',
        'optimized_at',
        'start_label',
        'start_latitude',
        'start_longitude',
    ];

    protected $casts = [
        'tour_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'optimization_enabled' => 'boolean',
        'route_is_complete' => 'boolean',
        'optimized_at' => 'datetime',
        'start_latitude' => 'float',
        'start_longitude' => 'float',
        'vehicle_capacity_kg' => 'float',
        'vehicle_volume_m3' => 'float',
    ];

    public function driver()
    {
        return $this->belongsTo(DeliveryDriver::class, 'driver_id');
    }

    public function stops()
    {
        return $this->hasMany(DeliveryTourStop::class)->orderBy('sequence');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'in_progress' => 'En cours',
            'late' => 'En retard',
            'planned' => 'Planifiée',
            'done' => 'Terminée',
            'cancelled' => 'Annulée',
            default => ucfirst($this->status),
        };
    }
}
