<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'path',
        'original_path',
        'card_path',
        'thumb_path',
        'original_width',
        'original_height',
        'normalized_at',
        'url',
        'image',
        'file_path',
        'filename',
        'alt',
        'is_main',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'is_main' => 'boolean',
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
        'original_width' => 'integer',
        'original_height' => 'integer',
        'normalized_at' => 'datetime',
    ];

    protected $appends = [
        'public_url',
        'card_url',
        'thumb_url',
        'original_url',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return $this->resolveStoragePath(
            $this->path
                ?: $this->image_path
                ?: $this->original_path
                ?: $this->card_path
                ?: $this->thumb_path
                ?: $this->url
                ?: $this->image
                ?: $this->file_path
                ?: $this->filename
        );
    }

    public function getCardUrlAttribute(): string
    {
        return $this->resolveStoragePath(
            $this->card_path
                ?: $this->path
                ?: $this->thumb_path
                ?: $this->original_path
                ?: $this->image_path
                ?: $this->url
                ?: $this->image
                ?: $this->file_path
                ?: $this->filename
        );
    }

    public function getThumbUrlAttribute(): string
    {
        return $this->resolveStoragePath(
            $this->thumb_path
                ?: $this->card_path
                ?: $this->path
                ?: $this->original_path
                ?: $this->image_path
                ?: $this->url
                ?: $this->image
                ?: $this->file_path
                ?: $this->filename
        );
    }

    public function getOriginalUrlAttribute(): string
    {
        return $this->resolveStoragePath(
            $this->original_path
                ?: $this->path
                ?: $this->image_path
                ?: $this->url
                ?: $this->image
                ?: $this->file_path
                ?: $this->filename
        );
    }

    /**
     * Retourne toujours une chaîne et ne réalise aucun test d'existence disque.
     */
    private function resolveStoragePath(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return asset('images/product-placeholder.svg');
        }

        $path = trim(str_replace('\\', '/', $value));

        if (Str::startsWith($path, ['http://', 'https://', '//', 'data:image'])) {
            return $path;
        }

        if (preg_match('#(?:^|/)storage/(.+)$#i', $path, $matches) === 1) {
            return asset('storage/' . ltrim($matches[1], '/'));
        }

        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'public/storage/')) {
            return asset('storage/' . Str::after($path, 'public/storage/'));
        }

        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        if (Str::startsWith($path, 'app/public/')) {
            $path = Str::after($path, 'app/public/');
        } elseif (Str::startsWith($path, 'public/')) {
            $path = Str::after($path, 'public/');

            if (Str::startsWith($path, 'storage/')) {
                return asset($path);
            }
        }

        if (Str::startsWith($path, ['images/', 'assets/', 'build/'])) {
            return asset($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
