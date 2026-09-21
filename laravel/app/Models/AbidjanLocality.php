<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbidjanLocality extends Model
{
    protected $fillable = [
        'commune_id',
        'quarter_id',
        'commune',
        'quartier',
        'sous_quartier',
        'cite',
        'village',
        'carrefour',
        'alias_google_maps',
        'name',
        'slug',
        'type',
        'search_text',
        'latitude',
        'longitude',
        'source',
        'source_reference',
        'notes',
        'is_verified',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'alias_google_maps' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function communeRelation(): BelongsTo
    {
        return $this->belongsTo(AbidjanCommune::class, 'commune_id');
    }

    public function legacyQuarter(): BelongsTo
    {
        return $this->belongsTo(AbidjanQuarter::class, 'quarter_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'sous_quartier' => 'Sous-quartier',
            'cite' => 'Cité',
            'village' => 'Village',
            'carrefour' => 'Carrefour',
            default => 'Quartier',
        };
    }
}
