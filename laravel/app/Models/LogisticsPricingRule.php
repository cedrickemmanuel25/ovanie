<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPricingRule extends Model
{
    protected $fillable = ['group', 'key', 'label', 'value', 'sort_order', 'is_active', 'meta'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];
}
