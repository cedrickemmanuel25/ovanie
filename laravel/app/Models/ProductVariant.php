<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id','type','value','color_hex','stock','price_override',
        'image_path','card_path','thumb_path','is_active','sort_order',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'stock' => 'integer',
        'price_override' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['label','image_url','card_url','thumb_url'];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    public function getLabelAttribute(): string
    {
        return trim(($this->type ?: 'Modèle') . ' : ' . $this->value);
    }

    public function getImageUrlAttribute(): string { return $this->publicUrl($this->image_path ?: $this->card_path ?: $this->thumb_path); }
    public function getCardUrlAttribute(): string { return $this->publicUrl($this->card_path ?: $this->image_path ?: $this->thumb_path); }
    public function getThumbUrlAttribute(): string { return $this->publicUrl($this->thumb_path ?: $this->card_path ?: $this->image_path); }

    private function publicUrl(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') return '';
        if (Str::startsWith($path, ['http://','https://','//'])) return $path;
        $path = ltrim(str_replace('\\','/',$path), '/');
        if (Str::startsWith($path, 'storage/')) return asset($path);
        return asset('storage/' . preg_replace('#^public/#', '', $path));
    }
}
