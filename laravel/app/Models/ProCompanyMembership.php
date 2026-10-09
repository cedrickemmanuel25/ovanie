<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProCompanyMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'pro_organization_id',
        'pro_user_id',
        'organization_name',
        'permission',
        'status',
    ];

    public function organization()
    {
        return $this->belongsTo(ProOrganization::class, 'pro_organization_id');
    }

    public function user()
    {
        return $this->belongsTo(ProUser::class, 'pro_user_id');
    }
}
