<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterProduct extends Model
{
    protected $fillable = [
        'name',
        'brand',
        'reference',
        'sku',
        'category_id',
        'unit',
        'packaging',
        'weight_kg',
        'volume_m3',
        'length_cm',
        'width_cm',
        'height_cm',
        'color',
        'grade',
        'standard',
        'description',
        'short_description',
        'technical_details',
        'product_attributes',
        'fragile',
        'requires_unloading',
        'unloading_instructions',
        'is_active',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'volume_m3' => 'float',
        'length_cm' => 'float',
        'width_cm' => 'float',
        'height_cm' => 'float',
        'product_attributes' => 'array',
        'fragile' => 'boolean',
        'requires_unloading' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
