<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommercialLead extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'shop_id', 'support_conversation_id', 'business_request_id', 'devis_id',
        'appel_offre_id', 'assigned_to', 'created_by', 'source', 'lead_type',
        'status', 'company_name', 'contact_name', 'email', 'phone', 'city',
        'sector', 'title', 'need_summary', 'estimated_value', 'probability',
        'expected_close_at', 'next_action_at', 'won_at', 'lost_at', 'lost_reason',
        'metadata',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'probability' => 'integer',
        'expected_close_at' => 'date',
        'next_action_at' => 'datetime',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommercialLead $lead) {
            $lead->reference ??= 'COM-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        });


        static::saving(function (CommercialLead $lead) {
            if ($lead->status === 'won' && ! $lead->won_at) {
                $lead->won_at = now();
            }
            if ($lead->status === 'lost' && ! $lead->lost_at) {
                $lead->lost_at = now();
            }
        });
    }

    public function user() { return $this->belongsTo(User::class); }
    public function shop() { return $this->belongsTo(Shop::class); }
    public function supportConversation() { return $this->belongsTo(SupportConversation::class, 'support_conversation_id'); }
    public function businessRequest() { return $this->belongsTo(BusinessRequest::class); }
    public function devis() { return $this->belongsTo(Devis::class); }
    public function appelOffre() { return $this->belongsTo(AppelOffre::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function activities() { return $this->hasMany(CommercialActivity::class)->latest('happened_at'); }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['won', 'lost', 'cancelled']);
    }
}
