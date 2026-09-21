<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    /**
     * Nom réel de la table.
     *
     * Laravel transforme automatiquement "WhatsAppMessage"
     * en "whats_app_messages".
     *
     * Or la migration du projet crée :
     * whatsapp_messages
     *
     * On force donc explicitement le bon nom.
     */
    protected $table = 'whatsapp_messages';

    /**
     * Champs pouvant être remplis en masse.
     */
    protected $fillable = [
        'support_conversation_id',
        'support_conversation_message_id',
        'direction',
        'provider_message_id',
        'phone_number_id',
        'from_phone',
        'to_phone',
        'message_type',
        'status',
        'body',
        'payload',
        'error_code',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    /**
     * Conversion automatique des colonnes.
     */
    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Conversation Support associée au message WhatsApp.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            SupportConversation::class,
            'support_conversation_id'
        );
    }

    /**
     * Message Support associé au message WhatsApp.
     */
    public function conversationMessage(): BelongsTo
    {
        return $this->belongsTo(
            SupportConversationMessage::class,
            'support_conversation_message_id'
        );
    }
}