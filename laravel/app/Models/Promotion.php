<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_product', 'promotion_id', 'product_id')
            ->withTimestamps();
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_promotion', 'promotion_id', 'order_id')
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'promotion_user', 'promotion_id', 'user_id')
            ->withTimestamps();
    }

    public function getStatusLabelAttribute(): string
    {
        if (! $this->is_active) {
            return 'Désactivée';
        }

        if ($this->starts_at?->isFuture()) {
            return 'Programmée';
        }

        if ($this->ends_at?->isPast()) {
            return 'Terminée';
        }

        return 'Active';
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status_label) {
            'Active' => 'green',
            'Programmée' => 'blue',
            'Terminée' => 'gray',
            default => 'orange',
        };
    }

    public function getValueLabelAttribute(): string
    {
        if ($this->type === 'percent') {
            return rtrim(rtrim(number_format((float) $this->value, 2, ',', ' '), '0'), ',') . ' %';
        }

        return number_format((float) $this->value, 0, ',', ' ') . ' FCFA';
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'percent' ? 'Réduction en pourcentage' : 'Réduction fixe';
    }
}
