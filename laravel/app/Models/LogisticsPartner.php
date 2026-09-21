<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPartner extends Model
{
    protected $guarded = [];

    protected $casts = [
        'coverage' => 'array',
        'vehicle_types' => 'array',
        'fleet_distribution' => 'array',
        'vehicles' => 'array',
        'mission_history' => 'array',
        'internal_notes' => 'array',
        'available_missions' => 'array',
        'meta' => 'array',
        'integrated_at' => 'date',
        'can_receive_missions' => 'boolean',
        'auto_assignment_visible' => 'boolean',
        'supports_special_loads' => 'boolean',
        'intercommunal_delivery' => 'boolean',
        'sla_percent' => 'float',
        'acceptance_percent' => 'float',
    ];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
