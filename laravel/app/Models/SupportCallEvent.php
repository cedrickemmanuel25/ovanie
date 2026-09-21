<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCallEvent extends Model
{
    protected $fillable = ['support_call_id', 'event', 'provider_event_id', 'payload', 'occurred_at'];
    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];
    public function call() { return $this->belongsTo(SupportCall::class, 'support_call_id'); }
}
