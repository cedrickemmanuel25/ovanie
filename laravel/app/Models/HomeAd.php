<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeAd extends Model
{
    protected $fillable = [
        'type',
        'placement',
        'title',
        'subtitle',
        'button_text',
        'button_url',
        'text',
        'media',
        'link',
        'duration',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration' => 'integer',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function getMediaUrlAttribute(): string
    {
        return asset('storage/' . $this->media);
    }
}