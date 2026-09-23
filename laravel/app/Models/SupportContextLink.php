<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportContextLink extends Model
{
    protected $fillable = [
        'support_ticket_id', 'context_type', 'context_id', 'is_primary', 'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    public function ticket() { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
}
