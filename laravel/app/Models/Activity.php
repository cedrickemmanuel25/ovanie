<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = [
        'activity_sector_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Une activité appartient à un secteur d’activité
     */
    public function sector()
    {
        return $this->belongsTo(ActivitySector::class, 'activity_sector_id');
    }
}
