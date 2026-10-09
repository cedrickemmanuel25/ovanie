<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProUserRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'pro_user_id',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(ProUser::class, 'pro_user_id');
    }

    public function profile()
    {
        return $this->hasOne(ProUserRoleProfile::class, 'pro_user_role_id');
    }
}
