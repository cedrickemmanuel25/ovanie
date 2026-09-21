<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
{
    protected $fillable = [
        'support_ticket_id', 'author_id', 'author_type', 'body',
        'is_internal_note', 'attachments',
    ];

    protected $casts = [
        'is_internal_note' => 'boolean',
        'attachments' => 'array',
    ];

    public function ticket() { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}
