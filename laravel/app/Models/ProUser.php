<?php

namespace App\Models;

use App\Notifications\ProResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class ProUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'pro_users';

    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'residence_country',
        'preferred_language',
        'password',
        'account_type',
        'onboarding_path',
        'onboarding_completed_at',
        'professional_profile',
        'status',
        'professional_status',
        'country',
        'locale',
        'currency',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'onboarding_completed_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /** Rôles métier : buyer_pro, seller_supplier, advertiser, requester. */
    public function roles()
    {
        return $this->hasMany(ProUserRole::class);
    }

    public function professionalProfile()
    {
        return $this->hasOne(ProProfessionalProfile::class, 'pro_user_id');
    }

    public function companyMemberships()
    {
        return $this->hasMany(ProCompanyMembership::class, 'pro_user_id');
    }

    /** Organisations dont le compte est propriétaire (création via ACC-04 à ACC-11). */
    public function ownedOrganizations()
    {
        return $this->hasMany(ProOrganization::class, 'owner_pro_user_id');
    }

    /** Adhésions actives : une adhésion désactivée retire immédiatement l'accès entreprise. */
    public function activeMemberships()
    {
        return $this->companyMemberships()->where('status', 'active')->whereNotNull('pro_organization_id');
    }

    /** Organisation principale : celle du compte, sinon la première rejointe par invitation. */
    public function primaryOrganization(): ?ProOrganization
    {
        $membership = $this->activeMemberships()->with('organization')->orderBy('id')->first();

        return $membership?->organization;
    }

    public function permissionIn(ProOrganization $organization): ?string
    {
        return $this->activeMemberships()
            ->where('pro_organization_id', $organization->id)
            ->value('permission');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('role', $role)->exists();
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: (string) ($this->name ?? '');
    }

    public function getProfessionalProfileLabelAttribute(): ?string
    {
        if (! $this->professional_profile) {
            return null;
        }

        return config('ovanie_pro.professional_profiles.' . $this->professional_profile . '.label')
            ?? $this->professional_profile;
    }

    public function appelOffres()
    {
        return $this->hasMany(AppelOffre::class, 'pro_user_id');
    }

    public function devis()
    {
        return $this->hasMany(Devis::class, 'pro_user_id');
    }

    public function businessRequests()
    {
        return $this->hasMany(BusinessRequest::class, 'pro_user_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'pro_user_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ProResetPasswordNotification($token));
    }
}
