<?php

namespace App\Models;

use App\Services\CommissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'created_by_commercial_id',
        'vendor_id',
        'category_id',
        'sku',
        'master_product_id',
        'fulfillment_priority',
        'is_fulfillment_enabled',
        'product_state',
        'name',
        'slug',
        'description',
        'short_description',
        'technical_details',
        'product_attributes',
        'technical_sheet_path',
        'product_video_path',
        'product_video_url',
        'usage_area',
        'material_grade',
        'color',
        'standard',
        'warranty',
        'return_policy',
        'price',
        'weight',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'volume_m3',
        'transport',
        'promo_price',
        'promo_end',
        'stock',
        'availability_status',
        'fast_delivery',
        'status',
        'is_active',
        'commission_paid',
        'lock_contacts',
        'gallery',
        'type',
        'sale_type',
        'unit',
        'unit_label',
        'min_order_quantity',
        'packaging',
        'content_per_unit',
        'content_unit',
        'units_per_package',
        'coverage_per_unit_m2',
        'supply_delay',
        'brand',
        'origin_country',
        'delivery_mode',
        'seller_delivery_delay',
        'fragile',
        'requires_unloading',
        'unloading_instructions',
        'handling_options',
        'pickup_city',
        'pickup_commune',
        'pickup_address',
        'price_p1',
        'price_p2',
        'price_p3',
        'is_negotiable',
        'flash_start_at',
        'flash_end',
        'bf_start',
        'bf_end',
        'is_boosted',
        'boosted_by_ovanie',
        'boost_type',
        'boost_start_at',
        'boost_end_at',
        'boost_price',
        'boost_payment_status',
        'boost_payment_reference',
        'boost_paid_at',
        'boost_package',
        'boost_duration_days',
        'boost_priority',
        'boost_views',
        'boost_clicks',
        'free_boosted_by_ovanie',
        'free_boost_start_at',
        'free_boost_end_at',
        'free_boost_batch_date',
        'views',
        'sales',
        'archived_at',
        'archived_by',
        'archive_reason',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'master_product_id' => 'integer',
        'fulfillment_priority' => 'integer',
        'is_fulfillment_enabled' => 'boolean',
        'category_id' => 'integer',
        'price' => 'integer',
        'weight' => 'float',
        'weight_kg' => 'float',
        'length_cm' => 'float',
        'width_cm' => 'float',
        'height_cm' => 'float',
        'volume_m3' => 'float',
        'transport' => 'float',
        'promo_price' => 'integer',
        'stock' => 'integer',
        'min_order_quantity' => 'integer',
        'content_per_unit' => 'float',
        'units_per_package' => 'integer',
        'coverage_per_unit_m2' => 'float',
        'price_p1' => 'integer',
        'price_p2' => 'integer',
        'price_p3' => 'integer',
        'views' => 'integer',
        'sales' => 'integer',
        'gallery' => 'array',
        'product_attributes' => 'array',
        'handling_options' => 'array',
        'commission_paid' => 'boolean',
        'lock_contacts' => 'boolean',
        'is_negotiable' => 'boolean',
        'is_active' => 'boolean',
        'fast_delivery' => 'boolean',
        'fragile' => 'boolean',
        'requires_unloading' => 'boolean',
        'is_boosted' => 'boolean',
        'boosted_by_ovanie' => 'boolean',
        'free_boosted_by_ovanie' => 'boolean',
        'archived_by' => 'integer',
        'boost_duration_days' => 'integer',
        'boost_priority' => 'integer',
        'boost_views' => 'integer',
        'boost_clicks' => 'integer',
        'promo_end' => 'datetime',
        'flash_start_at' => 'datetime',
        'flash_end' => 'datetime',
        'bf_start' => 'datetime',
        'bf_end' => 'datetime',
        'boost_start_at' => 'datetime',
        'boost_end_at' => 'datetime',
        'boost_paid_at' => 'datetime',
        'free_boost_start_at' => 'datetime',
        'free_boost_end_at' => 'datetime',
        'free_boost_batch_date' => 'date',
        'archived_at' => 'datetime',
    ];

    /**
     * Les montants FCFA n'ont pas de sous-unité dans OVANIE.
     * Toute écriture produit est donc normalisée au franc entier le plus proche,
     * quel que soit le point d'entrée (Web, API vendeur, API commercial, import).
     */
    public function setPriceAttribute(mixed $value): void
    {
        $this->attributes['price'] = $this->normalizeFcfa($value) ?? 0;
    }

    public function setPromoPriceAttribute(mixed $value): void
    {
        $this->attributes['promo_price'] = $this->normalizeFcfa($value);
    }

    public function setPriceP1Attribute(mixed $value): void
    {
        $this->attributes['price_p1'] = $this->normalizeFcfa($value);
    }

    public function setPriceP2Attribute(mixed $value): void
    {
        $this->attributes['price_p2'] = $this->normalizeFcfa($value);
    }

    public function setPriceP3Attribute(mixed $value): void
    {
        $this->attributes['price_p3'] = $this->normalizeFcfa($value);
    }

    private function normalizeFcfa(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(["\u{00A0}", ' '], '', trim($value));
            $value = str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) round((float) $value));
    }

    protected $appends = [
        'main_image_url',
        'card_image_url',
        'thumb_image_url',
        'image_url',
        'images_urls',
        'gallery_urls',
        'display_price',
        'base_price',
        'commission_amount',
        'final_price',
        'normal_public_price',
        'is_on_promo',
        'negotiation_prices',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function masterProduct()
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function images()
    {
        // Ne pas utiliser sort_order ici : ta table product_images ne possède pas toujours cette colonne.
        return $this->hasMany(ProductImage::class)->orderByDesc('id');
    }

    public function productImages()
    {
        return $this->images();
    }

    public function deliveryZones()
    {
        return $this->hasMany(ProductDeliveryZone::class)->where('is_active', true);
    }

    public function deliveryServices()
    {
        return $this->belongsToMany(DeliveryService::class, 'product_delivery_service')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function negotiations()
    {
        return $this->hasMany(Negotiation::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $hasCondition = false;

            if ($this->tableHasColumn('status')) {
                $q->orWhereIn('status', ['actif', 'active', 'approved']);
                $hasCondition = true;
            }

            if ($this->tableHasColumn('is_active')) {
                $q->orWhere('is_active', true);
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $q->whereRaw('1 = 1');
            }
        });
    }

    public function scopeAvailable(Builder $query): Builder
    {
        if ($this->tableHasColumn('stock')) {
            return $query->where('stock', '>', 0);
        }

        return $query;
    }

    public function scopeNegotiable(Builder $query): Builder
    {
        if ($this->tableHasColumn('is_negotiable')) {
            return $query->where('is_negotiable', true);
        }

        return $query->whereNotNull('price_p1')
            ->whereNotNull('price_p2')
            ->whereNotNull('price_p3');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            if ($this->tableHasColumn('archived_at')) {
                $q->whereNull('archived_at');
            }

            if ($this->tableHasColumn('status')) {
                $q->where(function (Builder $statusQuery) {
                    $statusQuery->whereNull('status')
                        ->orWhere('status', '!=', 'archived');
                });
            }
        });
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $hasCondition = false;

            if ($this->tableHasColumn('archived_at')) {
                $q->orWhereNotNull('archived_at');
                $hasCondition = true;
            }

            if ($this->tableHasColumn('status')) {
                $q->orWhere('status', 'archived');
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    public function scopeVisibleForSale(Builder $query): Builder
    {
        return $query->notArchived()
            ->active()
            ->whereHas('shop', function (Builder $shop) {
                $shop->where('status', 'approved')
                    ->where('is_active', true)
                    ->where(function (Builder $q) {
                        $q->where('logistics_type', 'ovanie')
                            ->orWhere(function (Builder $seller) {
                                $seller->where('logistics_type', 'seller')
                                    ->whereHas('sellerDeliveryProfile', fn (Builder $profile) => $profile
                                        ->where('is_enabled', true)
                                        ->whereIn('status', ['pending_review', 'approved'])
                                        ->whereNotNull('default_delay')
                                        ->whereNotNull('max_weight_kg')
                                        ->whereNotNull('max_volume_m3')
                                        ->whereNotNull('conditions'))
                                    ->whereHas('sellerDeliveryZones', fn (Builder $zones) => $zones
                                        ->where('is_active', true)
                                        ->whereNotNull('commune'));
                            });
                    });
            });
    }

    public function scopeActiveBoosted(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where(function (Builder $q) use ($now) {
            $hasCondition = false;

            if ($this->tableHasColumn('is_boosted')) {
                $q->orWhere(function (Builder $boostedQuery) use ($now) {
                    $boostedQuery->where('is_boosted', true);

                    if ($this->tableHasColumn('boost_payment_status')) {
                        $boostedQuery->where(function (Builder $paymentQuery) {
                            $paymentQuery->whereNull('boost_payment_status')
                                ->orWhereIn('boost_payment_status', ['paid', 'success', 'completed']);
                        });
                    }

                    $this->applyBoostDateWindow($boostedQuery, $now, 'boost_start_at', 'boost_end_at');
                });

                $hasCondition = true;
            }

            if ($this->tableHasColumn('boosted_by_ovanie')) {
                $q->orWhere(function (Builder $boostedByOvanieQuery) use ($now) {
                    $boostedByOvanieQuery->where('boosted_by_ovanie', true);
                    $this->applyBoostDateWindow($boostedByOvanieQuery, $now, 'boost_start_at', 'boost_end_at');
                });

                $hasCondition = true;
            }

            if ($this->tableHasColumn('free_boosted_by_ovanie')) {
                $q->orWhere(function (Builder $freeBoostQuery) use ($now) {
                    $freeBoostQuery->where('free_boosted_by_ovanie', true);
                    $this->applyBoostDateWindow($freeBoostQuery, $now, 'free_boost_start_at', 'free_boost_end_at');
                });

                $hasCondition = true;
            }

            if (! $hasCondition) {
                $q->whereRaw('1 = 0');
            }
        })
            ->when($this->tableHasColumn('boost_priority'), fn (Builder $q) => $q->orderByDesc('boost_priority'))
            ->when($this->tableHasColumn('boost_paid_at'), fn (Builder $q) => $q->orderByDesc('boost_paid_at'))
            ->when($this->tableHasColumn('views'), fn (Builder $q) => $q->orderByDesc('views'))
            ->orderByDesc('created_at');
    }

    protected function applyBoostDateWindow(Builder $query, Carbon $now, string $startColumn, string $endColumn): void
    {
        if ($this->tableHasColumn($startColumn)) {
            $query->where(function (Builder $dateQuery) use ($now, $startColumn) {
                $dateQuery->whereNull($startColumn)->orWhere($startColumn, '<=', $now);
            });
        }

        if ($this->tableHasColumn($endColumn)) {
            $query->where(function (Builder $dateQuery) use ($now, $endColumn) {
                $dateQuery->whereNull($endColumn)->orWhere($endColumn, '>=', $now);
            });
        }
    }

    protected function tableHasColumn(string $column): bool
    {
        try {
            return Schema::hasTable($this->getTable()) && Schema::hasColumn($this->getTable(), $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Images produit
    |--------------------------------------------------------------------------
    */

    public function getMainImageUrlAttribute(): string
    {
        $image = $this->primaryProductImageForDisplay();

        if ($image instanceof ProductImage) {
            return $this->resolveImageUrl(
                $image->path
                    ?: $image->image_path
                    ?: $image->original_path
                    ?: $image->card_path
                    ?: $image->thumb_path
                    ?: $image->url
                    ?: $image->image
                    ?: $image->file_path
                    ?: $image->filename
            );
        }

        return $this->resolveImageUrl($this->getMainImagePath());
    }

    public function getCardImageUrlAttribute(): string
    {
        $image = $this->primaryProductImageForDisplay();

        if ($image instanceof ProductImage) {
            return $this->resolveImageUrl(
                $image->card_path
                    ?: $image->path
                    ?: $image->thumb_path
                    ?: $image->original_path
                    ?: $image->image_path
                    ?: $image->url
                    ?: $image->image
                    ?: $image->file_path
                    ?: $image->filename
            );
        }

        return $this->resolveImageUrl($this->getMainImagePath());
    }

    public function getThumbImageUrlAttribute(): string
    {
        $image = $this->primaryProductImageForDisplay();

        if ($image instanceof ProductImage) {
            return $this->resolveImageUrl(
                $image->thumb_path
                    ?: $image->card_path
                    ?: $image->path
                    ?: $image->original_path
                    ?: $image->image_path
                    ?: $image->url
                    ?: $image->image
                    ?: $image->file_path
                    ?: $image->filename
            );
        }

        return $this->resolveImageUrl($this->getMainImagePath());
    }

    public function getImageUrlAttribute(): string
    {
        return $this->card_image_url;
    }

    protected function primaryProductImageForDisplay(): ?ProductImage
    {
        try {
            $images = $this->relationLoaded('images')
                ? collect($this->getRelation('images'))
                : $this->images()
                    ->orderByDesc('is_main')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();
        } catch (\Throwable $exception) {
            return null;
        }

        return $images
            ->filter(fn ($image) => $image instanceof ProductImage)
            ->sortByDesc(function (ProductImage $image): int {
                if ((bool) $image->is_main) {
                    return 2;
                }

                return (bool) $image->is_primary ? 1 : 0;
            })
            ->first();
    }

    public function getImagesUrlsAttribute(): array
    {
        return $this->getGalleryUrlsAttribute();
    }

    public function getGalleryUrlsAttribute(): array
    {
        $paths = [];

        foreach ($this->extractPathsFromValue($this->getRawOriginal('gallery')) as $path) {
            $paths[] = $path;
        }

        foreach (['main_image', 'image', 'image_path', 'photo', 'thumbnail'] as $column) {
            $value = $this->getRawOriginal($column);

            if (is_string($value) && trim($value) !== '') {
                $paths[] = $value;
            }
        }

        foreach ($this->relatedImagePaths() as $path) {
            $paths[] = $path;
        }

        $urls = collect($paths)
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->map(fn ($path) => $this->resolveImageUrl($path))
            ->unique()
            ->values()
            ->all();

        return $urls !== []
            ? $urls
            : [$this->defaultProductImage()];
    }

    protected function getMainImagePath(): ?string
    {
        $galleryPaths = $this->extractPathsFromValue(
            $this->getRawOriginal('gallery')
        );

        foreach ($galleryPaths as $path) {
            if (is_string($path) && trim($path) !== '') {
                return $path;
            }
        }

        foreach (['main_image', 'image', 'image_path', 'photo', 'thumbnail'] as $column) {
            $value = $this->getRawOriginal($column);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $this->relatedImagePaths()[0] ?? null;
    }

    protected function extractPathsFromValue(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatten()
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->map(fn ($path) => trim($path))
            ->values()
            ->all();
    }

    protected function relatedImagePaths(): array
    {
        try {
            $images = $this->relationLoaded('images')
                ? collect($this->getRelation('images'))
                : $this->images()
                    ->orderByDesc('is_main')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();
        } catch (\Throwable $exception) {
            return [];
        }

        return $images
            ->filter(fn ($image) => $image instanceof ProductImage)
            ->map(fn (ProductImage $image) =>
                $image->card_path
                    ?: $image->path
                    ?: $image->thumb_path
                    ?: $image->original_path
                    ?: $image->image_path
                    ?: $image->url
                    ?: $image->image
                    ?: $image->file_path
                    ?: $image->filename
            )
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->values()
            ->all();
    }

    public function publicStorageUrl(string $path): string
    {
        return $this->resolveImageUrl($path);
    }

    /**
     * Convertit un chemin enregistré en URL publique sans interroger le disque.
     *
     * Important : aucun Storage::exists() ni file_exists() ici. Ces accesseurs
     * sont appelés plusieurs fois pendant le rendu d'une page catalogue et les
     * accès disque répétés pouvaient provoquer le dépassement des 30 secondes.
     */
    protected function resolveImageUrl(?string $path): string
    {
        if (! is_string($path) || trim($path) === '') {
            return $this->defaultProductImage();
        }

        $path = trim(str_replace('\\', '/', $path));

        if (Str::startsWith($path, ['http://', 'https://', '//', 'data:image'])) {
            return $path;
        }

        // Une URL ou un chemin absolu contenant /storage/ est ramené au chemin public.
        if (preg_match('#(?:^|/)storage/(.+)$#i', $path, $matches) === 1) {
            return asset('storage/' . ltrim($matches[1], '/'));
        }

        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'public/storage/')) {
            return asset('storage/' . Str::after($path, 'public/storage/'));
        }

        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        if (Str::startsWith($path, 'app/public/')) {
            $path = Str::after($path, 'app/public/');
        } elseif (Str::startsWith($path, 'public/')) {
            $path = Str::after($path, 'public/');

            if (Str::startsWith($path, 'storage/')) {
                return asset($path);
            }
        }

        // Ressources réellement stockées directement dans public/.
        if (Str::startsWith($path, ['images/', 'assets/', 'build/'])) {
            return asset($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    protected function defaultProductImage(): string
    {
        return asset('images/product-placeholder.svg');
    }

    /*
    |--------------------------------------------------------------------------
    | Prix / promo / négociation
    |--------------------------------------------------------------------------
    */

    public function getBasePriceAttribute(): float
    {
        $normalPrice = (float) $this->price;
        $promoPrice = (float) ($this->promo_price ?? 0);

        if ($promoPrice <= 0 || $promoPrice >= $normalPrice) {
            return $normalPrice;
        }

        return $this->isPromotionPriceActive() ? $promoPrice : $normalPrice;
    }

    public function getDisplayPriceAttribute(): float
    {
        return $this->base_price;
    }

    public function getCommissionAmountAttribute(): float
    {
        return (float) round($this->base_price * $this->commissionRate());
    }

    public function getFinalPriceAttribute(): float
    {
        return (float) round($this->base_price + $this->commission_amount);
    }

    public function getNormalPublicPriceAttribute(): float
    {
        $normalPrice = max(0, (float) $this->price);

        return (float) round($normalPrice * (1 + $this->commissionRate()));
    }

    private function commissionRate(): float
    {
        $rate = (float) config('marketplace.default_commission_rate', CommissionService::DEFAULT_RATE);

        if ($rate > 1) {
            $rate /= 100;
        }

        return min(1.0, max(0.0, $rate));
    }

    public function getIsOnPromoAttribute(): bool
    {
        $promoPrice = (float) ($this->promo_price ?? 0);

        return $promoPrice > 0
            && $promoPrice < (float) $this->price
            && $this->isPromotionPriceActive();
    }

    public function isFlashSaleActive(?Carbon $at = null): bool
    {
        $at = ($at ?: Carbon::now())->timezone(
            (string) config('homepage.promotions.timezone', 'Africa/Abidjan')
        );

        if ($this->normalizedSaleType() !== 'vente flash') {
            return false;
        }

        if ($this->flash_start_at && $this->flash_start_at->gt($at)) {
            return false;
        }

        return (bool) ($this->flash_end && $this->flash_end->gt($at));
    }

    public function isBlackFridayActive(?Carbon $at = null): bool
    {
        $at = ($at ?: Carbon::now())->timezone(
            (string) config('homepage.promotions.timezone', 'Africa/Abidjan')
        );

        if ($this->normalizedSaleType() !== 'black friday' || ! $at->isFriday()) {
            return false;
        }

        if ($this->bf_start && $this->bf_start->gt($at)) {
            return false;
        }

        if ($this->bf_end && $this->bf_end->lt($at)) {
            return false;
        }

        return true;
    }

    public function normalizedSaleType(): string
    {
        $value = mb_strtolower(trim((string) $this->sale_type));
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return match ($value) {
            'flash', 'flash sale', 'vente flash' => 'vente flash',
            'black friday' => 'black friday',
            'promo', 'promotion' => 'promotion',
            '', 'vente normale' => 'normal',
            default => $value,
        };
    }

    private function isPromotionPriceActive(): bool
    {
        $now = Carbon::now()->timezone(
            (string) config('homepage.promotions.timezone', 'Africa/Abidjan')
        );

        return match ($this->normalizedSaleType()) {
            'vente flash' => $this->isFlashSaleActive($now),
            'black friday' => $this->isBlackFridayActive($now),
            'promotion' => ! $this->promo_end || $this->promo_end->gt($now),
            default => ! $this->promo_end || $this->promo_end->gt($now),
        };
    }

    public function getNegotiationPricesAttribute(): array
    {
        if (! $this->is_negotiable) {
            return [];
        }

        return [
            'normal' => (int) $this->price,
            'offer_2' => $this->price_p1 ? (int) $this->price_p1 : null,
            'offer_3' => $this->price_p2 ? (int) $this->price_p2 : null,
            'final_offer' => $this->price_p3 ? (int) $this->price_p3 : null,
        ];
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format((float) $this->display_price, 0, ',', ' ') . ' FCFA';
    }

    public function getFormattedNormalPriceAttribute(): string
    {
        return number_format((float) $this->price, 0, ',', ' ') . ' FCFA';
    }

    public function getFormattedUnitPriceAttribute(): string
    {
        return number_format((float) ($this->final_price ?? $this->price), 0, ',', ' ') . ' FCFA / ' . $this->display_unit;
    }

    public function getBadgeAttribute(): ?string
    {
        if ($this->isFlashSaleActive()) {
            return 'flash_sale';
        }

        if ($this->isBlackFridayActive()) {
            return 'black_friday';
        }

        if ($this->is_on_promo) {
            return 'promo';
        }

        return null;
    }

    public function getIsNegotiableAttribute($value): bool
    {
        return (bool) $value || (filled($this->price_p1) && filled($this->price_p2) && filled($this->price_p3));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers BTP / affichage
    |--------------------------------------------------------------------------
    */

    public function isNegotiable(): bool
    {
        return (bool) $this->is_negotiable;
    }

    public function hasVideo(): bool
    {
        return $this->has_product_video;
    }

    public function getHasProductVideoAttribute(): bool
    {
        return filled($this->product_video_path) || filled($this->product_video_url);
    }

    public function getVideoUrlAttribute(): ?string
    {
        return $this->product_video_public_url;
    }

    public function getProductVideoPublicUrlAttribute(): ?string
    {
        if ($this->product_video_path) {
            return $this->resolveImageUrl($this->product_video_path);
        }

        return $this->product_video_url ?: null;
    }

    public function getProductVideoEmbedUrlAttribute(): ?string
    {
        $url = trim((string) ($this->product_video_url ?? ''));

        if ($url === '') {
            return null;
        }

        if (preg_match('#(?:youtube\.com/(?:watch\?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        if (preg_match('#vimeo\.com/(\d+)#', $url, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }

        return null;
    }

    public function getTechnicalSheetUrlAttribute(): ?string
    {
        if (! $this->technical_sheet_path) {
            return null;
        }

        return $this->resolveImageUrl($this->technical_sheet_path);
    }

    public function getDisplayUnitAttribute(): string
    {
        return $this->unit_label ?: match ($this->unit) {
            'sac' => 'sac',
            'tonne' => 'tonne',
            'm3' => 'm³',
            'm2' => 'm²',
            'ml' => 'mètre linéaire',
            'piece' => 'pièce',
            'palette' => 'palette',
            'rouleau' => 'rouleau',
            'seau' => 'seau',
            'carton' => 'carton',
            'paquet' => 'paquet',
            'barre' => 'barre',
            'bidon' => 'bidon',
            'kg' => 'kg',
            'litre' => 'litre',
            default => 'unité',
        };
    }

    public function getUnitLabelDisplayAttribute(): string
    {
        return $this->display_unit;
    }

    public function getStockLabelAttribute(): string
    {
        $unit = $this->display_unit;
        $stock = (int) ($this->stock ?? 0);

        if ($stock <= 0) {
            return 'Sur commande';
        }

        return number_format($stock, 0, ',', ' ') . ' ' . ($stock > 1 ? $unit . 's' : $unit) . ' disponibles';
    }

    public function getProductStateLabelAttribute(): string
    {
        return match ($this->product_state) {
            'reconditioned' => 'Reconditionné',
            'used' => 'Occasion',
            default => 'Neuf',
        };
    }

    public function getAvailabilityLabelAttribute(): string
    {
        return match ($this->availability_status) {
            'on_order' => 'Disponible sous commande',
            'preorder' => 'Précommande',
            'out_of_stock' => 'Rupture temporaire',
            default => 'En stock immédiat',
        };
    }

    public function getContentLabelAttribute(): ?string
    {
        if (! $this->content_per_unit && ! $this->content_unit) {
            return null;
        }

        $quantity = $this->content_per_unit
            ? rtrim(rtrim(number_format((float) $this->content_per_unit, 3, ',', ' '), '0'), ',')
            : null;

        return trim(collect([$quantity, $this->content_unit])->filter()->join(' '));
    }

    public function getDimensionsLabelAttribute(): ?string
    {
        if (! $this->length_cm && ! $this->width_cm && ! $this->height_cm) {
            return null;
        }

        $length = $this->length_cm ? number_format((float) $this->length_cm, 2, ',', ' ') : '-';
        $width = $this->width_cm ? number_format((float) $this->width_cm, 2, ',', ' ') : '-';
        $height = $this->height_cm ? number_format((float) $this->height_cm, 2, ',', ' ') : '-';

        return $length . ' × ' . $width . ' × ' . $height . ' cm';
    }

    public function getDeliveryModeLabelAttribute(): ?string
    {
        return match ($this->delivery_mode) {
            'seller' => filled($this->seller_delivery_delay) ? 'Livraison prise en charge sous ' . $this->seller_delivery_delay : 'Livraison prise en charge',
            'ovanie' => 'OVANIE Logistics',
            'partner' => 'Transporteur partenaire',
            'pickup' => 'Retrait en boutique',
            default => null,
        };
    }

    public function getDeliveryProviderLabelAttribute(): string
    {
        return $this->delivery_mode_label ?: 'À définir';
    }

    public function getSellerDeliveryDelayLabelAttribute(): ?string
    {
        if ($this->delivery_mode !== 'seller' || blank($this->seller_delivery_delay)) {
            return null;
        }

        return 'Livraison prise en charge sous ' . $this->seller_delivery_delay;
    }

    public function getHandlingOptionsLabelAttribute(): ?string
    {
        $items = collect($this->handling_options ?? [])->filter()->values();
        return $items->isNotEmpty() ? $items->join(', ') : null;
    }

    public function getLogisticsWeightKgAttribute(): float
    {
        return (float) ($this->weight_kg ?? $this->weight ?? 0);
    }

    public function getBtpTechnicalSpecsAttribute(): array
    {
        return array_filter([
            'Unité de vente' => $this->display_unit,
            'Quantité minimum' => $this->min_order_quantity ? $this->min_order_quantity . ' ' . $this->display_unit : null,
            'Conditionnement' => $this->packaging,
            'Contenu par unité' => $this->content_label,
            'Surface couverte / unité' => $this->coverage_per_unit_m2 ? number_format((float) $this->coverage_per_unit_m2, 2, ',', ' ') . ' m²' : null,
            'Disponibilité' => $this->availability_label,
            'Délai d’approvisionnement' => $this->supply_delay,
            'État du produit' => $this->product_state_label,
            'Marque' => $this->brand,
            'Origine' => $this->origin_country,
            'Usage recommandé' => $this->usage_area,
            'Grade / Classe' => $this->material_grade,
            'Poids' => $this->logistics_weight_kg ? number_format((float) $this->logistics_weight_kg, 2, ',', ' ') . ' kg' : null,
            'Dimensions' => $this->dimensions_label,
            'Volume' => $this->volume_m3 ? number_format((float) $this->volume_m3, 3, ',', ' ') . ' m³' : null,
            'Mode de livraison' => $this->delivery_mode_label,
            'Délai livraison vendeur' => $this->seller_delivery_delay,
            'Produit fragile' => $this->fragile ? 'Oui' : null,
            'Déchargement requis' => $this->requires_unloading ? ($this->unloading_instructions ?: 'Oui') : null,
            'Manutention' => $this->handling_options_label,
            'Garantie' => $this->warranty,
            'Politique de retour' => $this->return_policy,
        ]);
    }

    public function getIsArchivedAttribute(): bool
    {
        return ! is_null($this->archived_at) || $this->status === 'archived';
    }

    public function getIsBoostPaidAttribute(): bool
    {
        return in_array($this->boost_payment_status, ['paid', 'success', 'completed'], true);
    }

    public function getIsBoostPendingAttribute(): bool
    {
        return $this->boost_payment_status === 'pending';
    }

    public function getIsBoostActiveAttribute(): bool
    {
        if (! $this->is_boosted) {
            return false;
        }

        if ($this->boost_payment_status && ! $this->is_boost_paid) {
            return false;
        }

        $now = Carbon::now();

        if ($this->boost_start_at && $this->boost_start_at->gt($now)) {
            return false;
        }

        if ($this->boost_end_at && $this->boost_end_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (blank($product->slug) && filled($product->name)) {
                $product->slug = Str::slug($product->name) . '-' . Str::lower(Str::random(5));
            }

            $saleType = mb_strtolower(trim((string) $product->sale_type));
            $saleType = str_replace(['_', '-'], ' ', $saleType);
            $saleType = preg_replace('/\s+/', ' ', $saleType) ?: 'normal';

            $product->sale_type = match ($saleType) {
                'flash', 'flash sale', 'vente flash' => 'vente flash',
                'black friday' => 'black friday',
                'promo', 'promotion' => 'promotion',
                '', 'vente normale' => 'normal',
                default => $saleType,
            };

            if ($product->sale_type === 'vente flash') {
                $product->flash_start_at ??= Carbon::now();

                if (! $product->flash_end) {
                    $product->flash_end = $product->flash_start_at->copy()->addHours(
                        max(1, (int) config('homepage.promotions.flash_default_hours', 24))
                    );
                }
            }

            if ($product->sale_type !== 'vente flash') {
                $product->flash_start_at = null;
                $product->flash_end = null;
            }
        });
    }

}
