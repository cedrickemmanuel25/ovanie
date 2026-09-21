<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivitySector extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Secteur d'activité a plusieurs activités
     */
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Scope pour ne récupérer que les secteurs actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
