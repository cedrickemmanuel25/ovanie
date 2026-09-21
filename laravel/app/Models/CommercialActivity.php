<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialActivity extends Model
{
    protected $fillable = [
        'commercial_lead_id', 'author_id', 'type', 'subject', 'description',
        'outcome', 'happened_at', 'next_follow_up_at', 'metadata',
    ];

    protected $casts = [
        'happened_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function lead() { return $this->belongsTo(CommercialLead::class, 'commercial_lead_id'); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}
