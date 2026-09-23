<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use App\Models\BusinessRequest;
use App\Models\ClientPaymentMethod;
use App\Models\LoyaltyTransaction;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'created_by_commercial_id',
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'password',
        'whatsapp_phone',
        'whatsapp_verified_at',
        'role',
        'status',
        'is_admin',
        'avatar',
        'secondary_phone',
        'birth_date',
        'gender',
        'city',
        'account_type',
        'loyalty_points',
        'loyalty_debt',
        'preferred_cities',
        'favorite_categories',
        'notification_preferences',
        'locale',
        'currency',
        'timezone',
        'date_format',
        'referral_code',
        'deletion_in_progress',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'whatsapp_verified_at' => 'datetime',
        'birth_date' => 'date',
        'preferred_cities' => 'array',
        'favorite_categories' => 'array',
        'notification_preferences' => 'array',
        'loyalty_points' => 'integer',
        'loyalty_debt' => 'integer',
        'deletion_in_progress' => 'boolean',
    ];

    /**
     * ===================================
     * RELATIONS
     * ===================================
     */

    /**
     * Un utilisateur peut avoir UNE boutique
     */
    public function shop()
    {
        return $this->hasOne(Shop::class);
    }

    public function commercialCreator()
    {
        return $this->belongsTo(self::class, 'created_by_commercial_id');
    }

    public function commerciallyCreatedUsers()
    {
        return $this->hasMany(self::class, 'created_by_commercial_id');
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoriteProducts()
    {
        return $this->belongsToMany(Product::class, 'favorites')->withTimestamps();
    }

    public function removeFavoriteProduct(int $productId): void
    {
        $this->favorites()->where('product_id', $productId)->delete();
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(ClientPaymentMethod::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
    /**
     * ===================================
     * ACCESSORS (ULTRA IMPORTANT IMOo)
     * ===================================
     */

    /**
     * Nom complet automatique
     */
    public function getFullNameAttribute()
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /**
     * Vérifie si l'utilisateur est vendeur
     */
    public function getIsVendorAttribute()
    {
        return $this->shop()->exists();
    }

    /**
     * Vérifie si la boutique est active
     */
    public function getHasActiveShopAttribute()
    {
        return $this->shop()
            ->where('status', 'approved')
            ->where('is_active', true)
            ->exists();
    }


    // app/Models/User.php

    public function getIsAdminAttribute()
    {
        return (bool) ($this->attributes['is_admin'] ?? false);
    }

    public function isAdmin()
    {
        return (bool) $this->is_admin;
    }

    public function businessRequests()
    {
        // Assure-toi d’utiliser le bon modèle : BusinessRequest
        return $this->hasMany(BusinessRequest::class, 'user_id');
    }
    public function sentNegotiations()
    {
        return $this->hasMany(Negotiation::class, 'buyer_id');
    }

    public function receivedNegotiations()
    {
        return $this->hasMany(Negotiation::class, 'vendor_id');
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function supportRequesterProfiles()
    {
        return $this->hasMany(SupportRequester::class);
    }

    public function assignedSupportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    public function createdSupportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'created_by');
    }

    public function commercialLeads()
    {
        return $this->hasMany(CommercialLead::class, 'assigned_to');
    }

    public function prospectingMissions()
    {
        return $this->belongsToMany(CommercialProspectingMission::class, 'commercial_prospecting_mission_members', 'commercial_id', 'mission_id')
            ->withPivot(['assigned_at'])
            ->withTimestamps();
    }

    public function internalLoginLogs()
    {
        return $this->hasMany(InternalLoginLog::class);
    }

    public function supportConversations()
    {
        return $this->hasMany(SupportConversation::class, 'requester_user_id');
    }

    public function assignedSupportConversations()
    {
        return $this->hasMany(SupportConversation::class, 'assigned_to');
    }

    public function handledSupportCalls()
    {
        return $this->hasMany(SupportCall::class, 'handled_by');
    }

    public function assignedSupportHandoffs()
    {
        return $this->hasMany(SupportAgentHandoff::class, 'assigned_to');
    }

    public function assignedCallbackRequests()
    {
        return $this->hasMany(SupportCallbackRequest::class, 'assigned_to');
    }

    public function latestInternalLoginLog()
    {
        return $this->hasOne(InternalLoginLog::class)->latestOfMany();
    }

    public function isSupportAgent(): bool
    {
        return $this->role === 'support' && ($this->staffProfile?->is_active ?? true);
    }

    public function isCommercialAgent(): bool
    {
        return $this->role === 'commercial' && ($this->staffProfile?->is_active ?? true);
    }

    public function isLogisticsAgent(): bool
    {
        return $this->role === 'logistique' && ($this->staffProfile?->is_active ?? true);
    }

    public function isInternalUser(): bool
    {
        return (bool) $this->is_admin || in_array($this->role, config('staff.internal_roles', []), true);
    }

    public function hasStaffPermission(string $permission): bool
    {
        if (! in_array($this->role, config('staff.managed_roles', []), true)) {
            return false;
        }

        $permissions = $this->staffProfile?->permissions
            ?? config('staff.role_permissions.' . $this->role, []);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

}
