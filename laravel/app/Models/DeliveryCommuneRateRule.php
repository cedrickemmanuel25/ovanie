<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryCommuneRateRule extends Model
{
    public const RELATION_SAME_COMMUNE = 'same_commune';
    public const RELATION_NEIGHBORING_COMMUNE = 'neighboring_commune';
    public const RELATION_DISTANT_COMMUNE = 'distant_commune';
    public const RELATION_INTER_CITY = 'inter_city';
    public const RELATION_UNKNOWN = 'unknown';

    protected $fillable = [
        'origin_commune',
        'origin_commune_id',
        'destination_commune',
        'destination_commune_id',
        'vehicle_code',
        'relation_type',
        'relation_fee',
        'min_fee',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'relation_fee' => 'float',
        'min_fee' => 'float',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function destinationCommune()
    {
        return $this->belongsTo(AbidjanCommune::class, 'destination_commune_id');
    }

    public function originCommune()
    {
        return $this->belongsTo(AbidjanCommune::class, 'origin_commune_id');
    }
}
