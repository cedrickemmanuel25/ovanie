<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbidjanLandmark extends Model
{
    protected $fillable = [
        'commune_id',
        'quarter_id',
        'name',
        'slug',
        'category',
        'address',
        'latitude',
        'longitude',
        'source',
        'source_reference',
        'is_verified',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(AbidjanCommune::class, 'commune_id');
    }

    public function quarter(): BelongsTo
    {
        return $this->belongsTo(AbidjanQuarter::class, 'quarter_id');
    }
}
