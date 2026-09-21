<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Carrier extends Model
{
    protected $fillable = [
        'name',
        'code',
        'phone',
        'city',
        'status',
        'rating',
        'average_delay_hours',
        'is_active',
    ];

    protected $casts = [
        'rating' => 'float',
        'average_delay_hours' => 'integer',
        'is_active' => 'boolean',
    ];


    public function partnerProfile()
    {
        // Compatibilité avec les installations OVANIE qui n'ont pas encore
        // exécuté la migration ajoutant logistics_partners.carrier_id.
        if (Schema::hasColumn('logistics_partners', 'carrier_id')) {
            return $this->hasOne(LogisticsPartner::class, 'carrier_id');
        }

        return $this->hasOne(LogisticsPartner::class, 'code', 'code');
    }

    public function vehicles()
    {
        return $this->hasMany(CarrierVehicle::class);
    }

    public function rateCards()
    {
        return $this->hasMany(CarrierRateCard::class);
    }

    public function deliveryQuotes()
    {
        return $this->hasMany(DeliveryQuote::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function deliveryServices()
    {
        return $this->hasMany(DeliveryService::class);
    }
}
