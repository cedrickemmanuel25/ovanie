<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppWebhookEvent extends Model
{
    /**
     * Laravel convertirait automatiquement WhatsAppWebhookEvent
     * en "whats_app_webhook_events".
     *
     * Or la vraie table créée par la migration est :
     * "whatsapp_webhook_events".
     *
     * On fixe donc explicitement le nom de la table.
     */
    protected $table = 'whatsapp_webhook_events';

    protected $fillable = [
        'event_key',
        'object_type',
        'signature_valid',
        'status',
        'attempts',
        'payload',
        'error',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'attempts' => 'integer',
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}