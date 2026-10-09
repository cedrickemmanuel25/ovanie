<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Organisation / entreprise OVANIE PRO : entité distincte du compte personnel.
 * Un compte peut la créer (propriétaire) ou la rejoindre sur invitation (collaborateur).
 */
class ProOrganization extends Model
{
    use HasFactory;

    public const VERIFICATION_STATUSES = [
        'draft' => 'Brouillon',
        'pending' => 'En attente de vérification',
        'in_progress' => 'Vérification en cours',
        'additional_info_requested' => 'Compléments demandés',
        'verified' => 'Vérifiée',
        'refused' => 'Refusée',
        'suspended' => 'Suspendue',
    ];

    protected $fillable = [
        'owner_pro_user_id',
        'entity_type',
        'legal_name',
        'trade_name',
        'country',
        'registration_country',
        'city',
        'address',
        'legal_form',
        'registration_number',
        'tax_number',
        'founded_year',
        'website',
        'official_email',
        'official_phone',
        'verification_status',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'founded_year' => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(ProUser::class, 'owner_pro_user_id');
    }

    public function memberships()
    {
        return $this->hasMany(ProCompanyMembership::class, 'pro_organization_id');
    }

    public function invitations()
    {
        return $this->hasMany(ProCompanyInvitation::class, 'pro_organization_id');
    }

    public function documents()
    {
        return $this->hasMany(ProDocument::class, 'pro_organization_id');
    }

    public function professionalProfile()
    {
        return $this->hasOne(ProProfessionalProfile::class, 'pro_organization_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->trade_name ?: $this->legal_name ?: 'Entreprise sans nom');
    }

    public function getVerificationLabelAttribute(): string
    {
        return self::VERIFICATION_STATUSES[$this->verification_status] ?? (string) $this->verification_status;
    }
}
