<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbidjanCommune extends Model
{
    protected $fillable = [
        'code',
        'name',
        'slug',
        'aliases',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function quarters(): HasMany
    {
        return $this->hasMany(AbidjanQuarter::class, 'commune_id');
    }

    public function localities(): HasMany
    {
        return $this->hasMany(AbidjanLocality::class, 'commune_id');
    }

    /**
     * Communes réellement proposables à l'inscription d'un livreur : actives
     * ET rattachées à au moins une zone Territoire elle-même active. Une
     * commune désactivée, ou dont toutes les zones sont désactivées par la
     * Logistique, ne doit plus apparaître dans ce parcours. Ce scope ne doit
     * PAS être utilisé pour le formulaire de création de zone côté Logistique
     * (voir LogisticsTerritoryController::availableCommunes()), où toutes les
     * communes actives restent sélectionnables même si pas encore rattachées
     * à une zone active.
     */
    public function scopeAvailableForOnboarding(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('logistics_territory_zone_communes as ltzc')
                ->join('logistics_territory_zones as ltz', 'ltz.id', '=', 'ltzc.zone_id')
                ->whereColumn('ltzc.commune_id', 'abidjan_communes.id')
                ->where('ltz.is_active', true);
        });
    }

    public static function isAvailableForOnboarding(int $id): bool
    {
        return static::query()->availableForOnboarding()->whereKey($id)->exists();
    }
}
