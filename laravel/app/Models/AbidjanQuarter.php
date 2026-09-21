<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbidjanQuarter extends Model
{
    protected $fillable = [
        'commune_id',
        'parent_id',
        'name',
        'slug',
        'normalized_name',
        'aliases',
        'search_terms',
        'type',
        'latitude',
        'longitude',
        'source',
        'source_reference',
        'source_metadata',
        'priority',
        'is_verified',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'search_terms' => 'array',
            'source_metadata' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(AbidjanCommune::class, 'commune_id');
    }

    public function landmarks(): HasMany
    {
        return $this->hasMany(AbidjanLandmark::class, 'quarter_id');
    }
}

