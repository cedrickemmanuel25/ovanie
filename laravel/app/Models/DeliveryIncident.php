<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeliveryIncident extends Model
{
    protected $fillable = [
        'meta',
        'order_id',
        'order_item_id',
        'shipment_id',
        'reported_by_type',
        'reported_by_id',
        'incident_type',
        'severity',
        'responsibility',
        'description',
        'latitude',
        'longitude',
        'occurred_at',
        'photo_path',
        'status',
        'next_action',
        'resolution_note',
        'resolved_by',
        'resolved_at',
        'rescheduled_at',
        'notified_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
        'rescheduled_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    /**
     * Exclut les dossiers créés par les seeders/données de démonstration.
     *
     * Le module Incidents de l'espace Logistique ne doit présenter que des
     * incidents rattachés aux opérations réelles. Le filtre reste défensif :
     * même si un seeder de démonstration est relancé en local, ces lignes ne
     * réapparaissent pas dans les écrans opérationnels.
     */
    public function scopeOperationalReal(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q) {
                $q->whereNull('reported_by_type')
                    ->orWhere('reported_by_type', 'not like', 'demo%');
            })
            ->whereDoesntHave('order', function (Builder $q) {
                $q->where('order_number', 'like', '%DEMO%');
            })
            ->whereDoesntHave('shipment', function (Builder $q) {
                $q->where('tracking_number', 'like', '%DEMO%');
            })
            ->whereDoesntHave('orderItem.deliveryAssignments', function (Builder $q) {
                $q->where('mission_number', 'like', '%DEMO%');
            });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function supportConversations()
    {
        return $this->hasMany(SupportConversation::class);
    }

    public function supportHandoffs()
    {
        return $this->hasMany(SupportAgentHandoff::class);
    }
}
