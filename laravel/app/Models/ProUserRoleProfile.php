<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProUserRoleProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'pro_user_role_id',
        'business_name',
        'activity',
        'country',
        'city',
        'professional_email',
        'professional_phone',
        'profile_data',
        'status',
    ];

    protected $casts = [
        'profile_data' => 'array',
    ];

    public function role()
    {
        return $this->belongsTo(ProUserRole::class, 'pro_user_role_id');
    }
}
