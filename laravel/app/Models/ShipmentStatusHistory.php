<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentStatusHistory extends Model
{
    protected $fillable = [
        'shipment_id',
        'status',
        'label',
        'note',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
