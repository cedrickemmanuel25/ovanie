<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProCompanyInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'pro_organization_id',
        'inviter_pro_user_id',
        'organization_name',
        'email',
        'phone',
        'permission',
        'token_hash',
        'status',
        'expires_at',
        'accepted_pro_user_id',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(ProOrganization::class, 'pro_organization_id');
    }

    public function inviter()
    {
        return $this->belongsTo(ProUser::class, 'inviter_pro_user_id');
    }

    public function acceptedUser()
    {
        return $this->belongsTo(ProUser::class, 'accepted_pro_user_id');
    }
}
