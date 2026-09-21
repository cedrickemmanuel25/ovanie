<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LogisticsTerritoryZone extends Model
{
    protected $fillable = [
        'code', 'name', 'region', 'is_active', 'communes_count', 'covered_communes',
        'average_delay_hours', 'activity_7d', 'activity_growth_percent', 'current_load_percent',
        'drivers_available', 'drivers_total', 'responsible_name', 'coverage_start_time',
        'coverage_end_time', 'operational_note', 'average_distance_km', 'zone_type',
        'delivery_density', 'sla_percent', 'allow_express', 'prioritize_missions',
        'auto_apply_new_missions', 'show_in_filters', 'drivers_snapshot', 'activity_snapshot',
        'map_variant', 'meta', 'source', 'coverage_geojson', 'center_latitude', 'center_longitude',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'communes_count' => 'integer',
        'covered_communes' => 'array',
        'average_delay_hours' => 'integer',
        'activity_7d' => 'integer',
        'activity_growth_percent' => 'float',
        'current_load_percent' => 'integer',
        'drivers_available' => 'integer',
        'drivers_total' => 'integer',
        'average_distance_km' => 'float',
        'sla_percent' => 'integer',
        'allow_express' => 'boolean',
        'prioritize_missions' => 'boolean',
        'auto_apply_new_missions' => 'boolean',
        'show_in_filters' => 'boolean',
        'drivers_snapshot' => 'array',
        'activity_snapshot' => 'array',
        'meta' => 'array',
        'coverage_geojson' => 'array',
        'center_latitude' => 'float',
        'center_longitude' => 'float',
    ];

    public function communes(): BelongsToMany
    {
        return $this->belongsToMany(
            AbidjanCommune::class,
            'logistics_territory_zone_communes',
            'zone_id',
            'commune_id'
        )->withTimestamps()->orderBy('abidjan_communes.name');
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
