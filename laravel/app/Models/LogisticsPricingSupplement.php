<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPricingSupplement extends Model
{
    protected $fillable = [
        'code', 'label', 'category', 'calculation_type', 'amount', 'scope',
        'condition_label', 'description', 'conditions', 'minimum_threshold',
        'compatible_with_others', 'automatic', 'is_active', 'meta',
    ];

    protected $casts = [
        'amount' => 'float',
        'conditions' => 'array',
        'minimum_threshold' => 'float',
        'compatible_with_others' => 'boolean',
        'automatic' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];
}
