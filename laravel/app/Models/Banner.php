<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'image',
        'link',
        'zone',
        'position',
        'is_active',
        'clicks'
    ];
}
