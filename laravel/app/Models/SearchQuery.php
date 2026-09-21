<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchQuery extends Model
{
    protected $fillable = [
        'term',
        'normalized_term',
        'category_slug',
        'searches_count',
        'last_searched_at',
    ];

    protected $casts = [
        'searches_count' => 'integer',
        'last_searched_at' => 'datetime',
    ];
}
