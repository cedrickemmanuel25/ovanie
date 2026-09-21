<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = [
        'name',
        'region',
        'price',
        'delivery_days',
        'is_active',
        'commune_id',
    ];

    protected $casts = [
        'price' => 'float',
        'delivery_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function commune()
    {
        return $this->belongsTo(AbidjanCommune::class, 'commune_id');
    }
}
