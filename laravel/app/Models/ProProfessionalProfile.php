<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProProfessionalProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'pro_organization_id',
        'pro_user_id',
        'profile_key',
        'entity_type',
        'activity_country',
        'registration_country',
        'desired_functions',
        'business_name',
        'trade_name',
        'activity',
        'country',
        'city',
        'address',
        'legal_form',
        'registration_number',
        'tax_number',
        'founded_year',
        'website',
        'responsible_position',
        'professional_email',
        'professional_phone',
        'professional_whatsapp',
        'profile_data',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'desired_functions' => 'array',
        'profile_data' => 'array',
        'submitted_at' => 'datetime',
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
