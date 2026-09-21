<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'title',
        'minimum_quantity',
        'unit',
        'professional_price',
        'lead_time',
        'status',
    ];

    protected $casts = [
        'minimum_quantity' => 'decimal:2',
        'professional_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
