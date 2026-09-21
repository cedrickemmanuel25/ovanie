<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportKnowledgeArticle extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'content', 'status', 'source_type',
        'source_reference', 'created_by', 'updated_by', 'approved_by',
        'published_at', 'reviewed_at', 'expires_at', 'version',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
        'version' => 'integer',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeAvailableToAi($query)
    {
        return $query->published()
            ->whereNotNull('approved_by')
            ->where(function ($builder) {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
