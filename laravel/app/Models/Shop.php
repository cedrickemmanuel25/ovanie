<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shop extends Model
{
    use HasFactory;

    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const KYC_PENDING = 'pending';
    public const KYC_VERIFIED = 'verified';
    public const KYC_REJECTED = 'rejected';

    public const LOGISTICS_READY = 'ready';
    public const LOGISTICS_INCOMPLETE = 'incomplete';
    public const LOGISTICS_SUSPENDED = 'suspended';

    public const PAYMENT_POST_DELIVERY = 'post_delivery';
    public const PAYMENT_WEEKLY = 'weekly';

    public const GEO_STATUS_RELIABLE = 'reliable';
    public const GEO_STATUS_REVIEW_RECOMMENDED = 'review_recommended';
    public const GEO_STATUS_VERIFICATION_REQUIRED = 'verification_required';
    public const GEO_STATUS_VERIFIED = 'verified';

    protected $fillable = [
        'user_id',
        'created_by_commercial_id',
        'managed_by_commercial_id',
        'name',
        'display_name',
        'slug',
        'description',
        'logo',
        'selfie',
        'seller_type',
        'company_name',
        'legal_form',
        'rccm',
        'taxpayer_number',
        'main_category',
        'main_subcategory',
        'commercial_notes',
        'delivery_zone',
        'processing_time',
        'city',
        'region',
        'commune',
        'commune_id',
        'district',
        'quarter_id',
        'landmark',
        'landmark_id',
        'address',
        'whatsapp',
        'business_email',
        'latitude',
        'longitude',
        'geo_accuracy',
        'geo_source',
        'geo_precision',
        'geo_precision_score',
        'geo_status',
        'geo_verified_at',
        'identity_country',
        'identity_type',
        'identity_number',
        'identity_upload_mode',
        'identity_file',
        'identity_file_front',
        'identity_file_back',
        'rccm_file',
        'tax_file',
        'mm_operator',
        'mm_number',
        'mm_holder',
        'direct_payment',
        'logistics_type',
        'payment_mode',
        'payout_onboarding_started_at',
        'status',
        'kyc_status',
        'logistics_status',
        'is_active',
        'rejection_reason',
        'approved_at',
        'reviewed_by',
    ];

    protected $casts = [
        'direct_payment' => 'boolean',
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'geo_accuracy' => 'float',
        'geo_precision_score' => 'integer',
        'geo_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'payout_onboarding_started_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function commercialCreator()
    {
        return $this->belongsTo(User::class, 'created_by_commercial_id');
    }

    public function commercialManager()
    {
        return $this->belongsTo(User::class, 'managed_by_commercial_id');
    }

    public function officialCommune(): BelongsTo
    {
        return $this->belongsTo(AbidjanCommune::class, 'commune_id');
    }

    public function officialQuarter(): BelongsTo
    {
        return $this->belongsTo(AbidjanQuarter::class, 'quarter_id');
    }

    public function officialLandmark(): BelongsTo
    {
        return $this->belongsTo(AbidjanLandmark::class, 'landmark_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Catégories dans lesquelles la boutique vend des produits (plusieurs
     * possibles). main_category reste renseignée en lecture seule pour la
     * compatibilité avec le code existant (affichage, filtres simples) et
     * correspond toujours à la première catégorie sélectionnée.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'shop_category')
            ->withTimestamps()
            ->orderBy('shop_category.id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function vendorPayouts()
    {
        return $this->hasMany(VendorPayout::class);
    }

    public function sellerDeliveryProfile()
    {
        return $this->hasOne(SellerDeliveryProfile::class);
    }

    public function sellerDeliveryZones()
    {
        return $this->hasMany(SellerDeliveryZone::class);
    }

    public function sellerDeliveryCapacities()
    {
        return $this->hasMany(SellerDeliveryCapacity::class);
    }

    public function getIsSellerLogisticsAttribute(): bool
    {
        return $this->usesSellerLogistics();
    }

    public function getIsOvanieLogisticsAttribute(): bool
    {
        return $this->usesOvanieLogistics();
    }

    public function getLogisticsModeLabelAttribute(): string
    {
        return $this->usesSellerLogistics()
            ? 'Logistique vendeur'
            : 'OVANIE Logistics';
    }

    public function getPaymentModeLabelAttribute(): string
    {
        return $this->payment_mode === self::PAYMENT_POST_DELIVERY
            ? 'Paiement après livraison (72 h)'
            : 'Paiement hebdomadaire';
    }

    public function usesSellerLogistics(): bool
    {
        return $this->logistics_type === 'seller';
    }

    public function usesOvanieLogistics(): bool
    {
        return ! $this->usesSellerLogistics();
    }

    public function hasCompleteSellerLogistics(): bool
    {
        if (! $this->usesSellerLogistics()) {
            return true;
        }

        $profile = $this->sellerDeliveryProfile;

        if (! $profile || ! $profile->is_enabled || ! filled($profile->default_delay) || ! filled($profile->conditions)) {
            return false;
        }

        if (! $profile->max_weight_kg || ! $profile->max_volume_m3) {
            return false;
        }

        return $this->sellerDeliveryZones()
            ->where('is_active', true)
            ->whereNotNull('commune')
            ->whereNotNull('delivery_price')
            ->whereNotNull('estimated_delay')
            ->exists();
    }

    public function getGeoStatusLabelAttribute(): string
    {
        return match ($this->geo_status) {
            self::GEO_STATUS_VERIFIED => 'Position vérifiée',
            self::GEO_STATUS_RELIABLE => 'Position fiable',
            self::GEO_STATUS_REVIEW_RECOMMENDED => 'Contrôle recommandé',
            default => 'Vérification requise',
        };
    }

    public function getGeoStatusSeverityAttribute(): string
    {
        return match ($this->geo_status) {
            self::GEO_STATUS_VERIFIED, self::GEO_STATUS_RELIABLE => 'success',
            self::GEO_STATUS_REVIEW_RECOMMENDED => 'warning',
            default => 'danger',
        };
    }

    public function hasCompleteOvaniePickupLocation(): bool
    {
        return filled($this->address)
            && filled($this->commune)
            && filled($this->district)
            && $this->latitude !== null
            && $this->longitude !== null
            && $this->geo_status !== self::GEO_STATUS_VERIFICATION_REQUIRED;
    }

    public function canPublishProducts(): bool
    {
        if ($this->status !== self::STATUS_APPROVED || ! $this->is_active) {
            return false;
        }

        if ($this->logistics_status === self::LOGISTICS_SUSPENDED) {
            return false;
        }

        if ($this->usesSellerLogistics()) {
            return $this->logistics_status === self::LOGISTICS_READY
                && $this->hasCompleteSellerLogistics();
        }

        return $this->logistics_status === self::LOGISTICS_READY
            && $this->hasCompleteOvaniePickupLocation();
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo
            ? asset('storage/' . $this->logo)
            : asset('images/default-shop.png');
    }

    public function getCanSellAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->is_active === true;
    }

    /**
     * Une boutique peut exposer des produits publiquement uniquement lorsque
     * son statut commercial et sa configuration logistique sont complets.
     *
     * Cette règle SQL reflète canPublishProducts() afin que l'accueil, le
     * catalogue, la recherche et la fiche produit utilisent la même éligibilité.
     */
    public function scopeCanPublishProducts(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_APPROVED)
            ->where('is_active', true)
            ->where('logistics_status', self::LOGISTICS_READY)
            ->where(function (Builder $logistics) {
                $logistics
                    ->where(function (Builder $ovanie) {
                        $ovanie->where(function (Builder $mode) {
                            $mode->whereNull('logistics_type')
                                ->orWhere('logistics_type', '!=', 'seller');
                        })
                            ->whereNotNull('address')->where('address', '!=', '')
                            ->whereNotNull('commune')->where('commune', '!=', '')
                            ->whereNotNull('district')->where('district', '!=', '')
                            ->whereNotNull('latitude')
                            ->whereNotNull('longitude');
                    })
                    ->orWhere(function (Builder $seller) {
                        $seller->where('logistics_type', 'seller')
                            ->whereHas('sellerDeliveryProfile', function (Builder $profile) {
                                $profile->where('is_enabled', true)
                                    ->whereNotNull('default_delay')->where('default_delay', '!=', '')
                                    ->whereNotNull('conditions')->where('conditions', '!=', '')
                                    ->where('max_weight_kg', '>', 0)
                                    ->where('max_volume_m3', '>', 0);
                            })
                            ->whereHas('sellerDeliveryZones', function (Builder $zones) {
                                $zones->where('is_active', true)
                                    ->whereNotNull('commune')->where('commune', '!=', '')
                                    ->whereNotNull('delivery_price')
                                    ->whereNotNull('estimated_delay')->where('estimated_delay', '!=', '');
                            });
                    });
            });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->where('is_active', true);
    }

    public function scopePendingKyc($query)
    {
        return $query->where('kyc_status', self::KYC_PENDING);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function supportConversations()
    {
        return $this->hasMany(SupportConversation::class);
    }

    public function commercialLeads()
    {
        return $this->hasMany(CommercialLead::class);
    }

}
