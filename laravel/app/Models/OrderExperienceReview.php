<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderExperienceReview extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'delivery_rating',
        'delivery_comment',
    ];

    protected $casts = [
        'delivery_rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
