<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'level',
        'name',
        'slug',
        'icon',
        'description',
        'status',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'level' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $category->name = trim((string) $category->name);
            $category->status = in_array($category->status, ['actif', 'active', '1', 1, true], true)
                ? 'actif'
                : 'inactif';
            $category->is_active = $category->status === 'actif';
            $category->level = $category->parent_id ? 2 : 1;

            if ($category->slug === null || $category->slug === '' || $category->isDirty('name')) {
                $category->slug = static::makeUniqueSlug($category->name, $category->getKey());
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function masterProducts()
    {
        return $this->hasMany(MasterProduct::class);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSubcategories(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $status): void {
            $status->whereNull('status')
                ->orWhereIn('status', ['actif', 'active', '1', 1]);
        })->where(function (Builder $active): void {
            $active->whereNull('is_active')->orWhere('is_active', true);
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getIsRootAttribute(): bool
    {
        return $this->parent_id === null;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status === 'actif' && $this->is_active !== false
            ? 'Active'
            : 'Inactive';
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->parent_id === null ? 'Catégorie principale' : 'Sous-catégorie';
    }

    public static function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'categorie';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
