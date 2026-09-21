<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeocodeCache extends Model
{
    protected $table = 'geocode_cache';

    protected $fillable = [
        'query',
        'provider',
        'country_code',
        'latitude',
        'longitude',
        'display_name',
        'raw_response',
        'expires_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'raw_response' => 'array',
        'expires_at' => 'datetime',
    ];
}
