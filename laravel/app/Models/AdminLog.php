<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'target_type',
        'target_id',
    ];

    /**
     * Un log appartient à un administrateur (utilisateur)
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
