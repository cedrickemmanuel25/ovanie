<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecentlyViewedProduct extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'views_count',
        'last_viewed_at',
    ];

    protected $casts = [
        'views_count' => 'integer',
        'last_viewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
