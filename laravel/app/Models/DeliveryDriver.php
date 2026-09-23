<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class DeliveryDriver extends Authenticatable
{
    use Notifiable;
    // Même mécanisme de jetons Sanctum que les autres apps mobiles OVANIE
    // (vendeur, commercial) : voir App\Http\Controllers\Api\Driver\DriverAuthController.
    use HasApiTokens;

    /**
     * Cycle de vie d'inscription d'un livreur partenaire.
     *
     * invited        : créé par la Logistique (nom/prénom/téléphone), n'a pas encore
     *                  ouvert l'app mobile "OVANIE Livreur".
     * pending_review : a soumis son dossier depuis l'app mobile, en attente de revue.
     * active         : dossier validé, le livreur peut recevoir des missions.
     * suspended      : validé puis suspendu par la Logistique.
     * rejected       : dossier refusé (voir rejection_reason).
     */
    public const ONBOARDING_INVITED = 'invited';

    public const ONBOARDING_PENDING_REVIEW = 'pending_review';

    public const ONBOARDING_ACTIVE = 'active';

    public const ONBOARDING_SUSPENDED = 'suspended';

    public const ONBOARDING_REJECTED = 'rejected';

    public const ONBOARDING_STATUSES = [
        self::ONBOARDING_INVITED,
        self::ONBOARDING_PENDING_REVIEW,
        self::ONBOARDING_ACTIVE,
        self::ONBOARDING_SUSPENDED,
        self::ONBOARDING_REJECTED,
    ];

    protected $fillable = [
        'profile',
        'name',
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'otp_code',
        'otp_expires_at',
        'otp_used_at',
        'otp_last_sent_at',
        'zone',
        'commune_id',
        'vehicle',
        'avatar',
        'status',
        'rating',
        'is_active',
        'must_change_password',
        'latitude',
        'longitude',
        'last_seen_at',
        'last_login_at',
        'phone_verified_at',
        'is_online',
        'onboarding_status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected static function booted(): void
    {
        // `name` reste calculé depuis first_name/last_name afin de ne pas casser
        // le reste de l'application (vues, exports CSV, services de mission, etc.)
        // qui utilise encore `$driver->name`.
        static::saving(function (DeliveryDriver $driver): void {
            if ($driver->isDirty('first_name') || $driver->isDirty('last_name')) {
                // La modification vient d'un flux qui connaît first_name/last_name
                // (invitation, app mobile) : `name` est recalculé pour rester cohérent.
                $driver->name = trim(trim((string) $driver->first_name).' '.trim((string) $driver->last_name));
            } elseif ($driver->isDirty('name') && filled($driver->name)) {
                // Compatibilité avec l'ancien formulaire d'édition qui ne connaît
                // que `name` : on répercute la saisie vers first_name/last_name.
                $parts = preg_split('/\s+/', trim((string) $driver->name)) ?: [];
                $driver->first_name = array_shift($parts) ?: $driver->name;
                $driver->last_name = trim(implode(' ', $parts));
            }

            if (! $driver->onboarding_status) {
                $driver->onboarding_status = self::ONBOARDING_INVITED;
            }
        });
    }

    public function commune()
    {
        return $this->belongsTo(AbidjanCommune::class, 'commune_id');
    }

    public function vehiclePhotoPath(): ?string
    {
        return data_get($this->profile, 'vehicle_photo')
            ?: data_get($this->profile, 'vehicle_photos.0');
    }

    protected $hidden = ['password', 'remember_token', 'otp_code'];

    protected $casts = [
        'profile' => 'array',
        'password' => 'hashed',
        'rating' => 'float',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'last_seen_at' => 'datetime',
        'last_login_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'is_online' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'otp_used_at' => 'datetime',
        'otp_last_sent_at' => 'datetime',
    ];

    public function reviewer()
    {
        return $this->belongsTo(InternalUser::class, 'reviewed_by');
    }

    /**
     * Indique si le livreur possède une présence GPS réellement fraîche.
     *
     * Le simple drapeau `is_online` n'est plus suffisant : il doit avoir été
     * activé par une remontée GPS et la dernière position doit encore être
     * récente. Cela évite qu'un ancien login OTP laisse un livreur affiché
     * « En ligne » alors que son application ou son GPS ne répond plus.
     */
    public function hasFreshGpsPresence(?int $seconds = null): bool
    {
        $rawOnline = (bool) ($this->attributes['is_online'] ?? false);
        if (! $rawOnline || ! $this->is_active || ! $this->isOnboardingActive()) {
            return false;
        }

        $seconds ??= (int) config('delivery.driver_presence_online_seconds', 120);
        $seconds = max(30, $seconds);

        $location = $this->relationLoaded('currentLocation')
            ? $this->getRelation('currentLocation')
            : $this->currentLocation()->first();

        if (! $location || ! $location->recorded_at) {
            return false;
        }

        $latitude = $location->latitude;
        $longitude = $location->longitude;
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }
        if ((float) $latitude === 0.0 && (float) $longitude === 0.0) {
            return false;
        }

        return $location->recorded_at->gte(now()->subSeconds($seconds));
    }

    /**
     * Accessor de compatibilité : partout où le projet lit `$driver->is_online`,
     * la valeur exposée représente maintenant une présence GPS fraîche.
     */
    public function getIsOnlineAttribute(mixed $value): bool
    {
        return $this->hasFreshGpsPresence();
    }

    /**
     * Scope SQL utilisé par les compteurs/cartes pour ne récupérer que les
     * livreurs actifs ayant réellement envoyé une position GPS récente.
     */
    public function scopeGpsOnline($query, ?int $seconds = null)
    {
        $seconds ??= (int) config('delivery.driver_presence_online_seconds', 120);
        $cutoff = now()->subSeconds(max(30, $seconds));

        return $query
            ->where('is_active', true)
            ->where('onboarding_status', self::ONBOARDING_ACTIVE)
            ->where('is_online', true)
            ->whereHas('locations', fn ($locations) => $locations->where('recorded_at', '>=', $cutoff));
    }

    /** Dernière date GPS réellement reçue, si disponible. */
    public function lastGpsSeenAt()
    {
        $location = $this->relationLoaded('currentLocation')
            ? $this->getRelation('currentLocation')
            : $this->currentLocation()->first();

        return $location?->recorded_at;
    }

    public function isOnboardingActive(): bool
    {
        return $this->onboarding_status === self::ONBOARDING_ACTIVE;
    }

    public function locations()
    {
        return $this->hasMany(DriverLocation::class, 'driver_id');
    }

    public function supportRequesterProfiles()
    {
        return $this->hasMany(SupportRequester::class, 'delivery_driver_id');
    }

    public function assignments()
    {
        return $this->hasMany(DeliveryAssignment::class, 'driver_id');
    }

    public function activeAssignments()
    {
        return $this->assignments()->whereIn('status', [
            'planned', 'assigned', 'accepted', 'collecting', 'picked_up', 'in_transit', 'arrived',
        ]);
    }

    public function currentLocation()
    {
        return $this->hasOne(DriverLocation::class, 'driver_id')->latestOfMany('recorded_at');
    }

    /**
     * Liste complète des communes dans lesquelles le livreur accepte des missions.
     * Le champ historique `zone` reste la commune principale pour compatibilité.
     * Les autres communes sont conservées dans profile.zones.
     *
     * @return array<int,string>
     */
    public function interventionZones(): array
    {
        $zones = collect(data_get($this->profile, 'zones', []))
            ->map(fn ($zone) => trim((string) $zone))
            ->filter();

        if (filled($this->zone)) {
            $zones->prepend(trim((string) $this->zone));
        }

        return $zones
            ->unique(fn ($zone) => $this->normaliseZoneName($zone))
            ->values()
            ->all();
    }

    /** @return array<int,int> */
    public function interventionZoneIds(): array
    {
        $ids = collect(data_get($this->profile, 'zone_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0);

        if ($this->commune_id) {
            $ids->prepend((int) $this->commune_id);
        }

        return $ids->unique()->values()->all();
    }

    /**
     * Indique si une commune fait partie des zones d'intervention du livreur.
     */
    public function coversZone(?string $zone, ?int $communeId = null): bool
    {
        if ($communeId && in_array($communeId, $this->interventionZoneIds(), true)) {
            return true;
        }

        $target = $this->normaliseZoneName((string) $zone);
        if ($target === '') {
            return false;
        }

        foreach ($this->interventionZones() as $driverZone) {
            $candidate = $this->normaliseZoneName($driverZone);
            if ($candidate === '') {
                continue;
            }

            if ($candidate === $target || str_contains($candidate, $target) || str_contains($target, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public function interventionZonesLabel(int $visible = 2): string
    {
        $zones = $this->interventionZones();
        if ($zones === []) {
            return 'Non renseignée';
        }

        $visible = max(1, $visible);
        $head = array_slice($zones, 0, $visible);
        $remaining = count($zones) - count($head);

        return implode(', ', $head).($remaining > 0 ? ' +'.$remaining : '');
    }

    private function normaliseZoneName(string $zone): string
    {
        $zone = Str::ascii($zone);
        $zone = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', $zone))));

        return $zone;
    }

    public function getInitialsAttribute(): string
    {
        return collect(preg_split('/\s+/', trim((string) $this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function hasPortalAccess(): bool
    {
        return filled($this->password) && $this->is_active;
    }
}
