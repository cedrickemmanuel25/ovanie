<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PromotionVisibilityService
{
    private const FLASH_TYPES = [
        'vente flash',
        'vente_flash',
        'flash sale',
        'flash_sale',
        'flash',
    ];

    private const BLACK_FRIDAY_TYPES = [
        'black friday',
        'black_friday',
    ];

    public function timezone(): string
    {
        return (string) config('homepage.promotions.timezone', 'Africa/Abidjan');
    }

    public function now(): Carbon
    {
        return now()->timezone($this->timezone());
    }

    public function isBlackFridayDay(?Carbon $at = null): bool
    {
        $local = ($at ?: $this->now())->copy()->timezone($this->timezone());

        return $local->dayOfWeekIso === (int) config('homepage.promotions.black_friday_weekday', 5);
    }

    public function applyActiveFlash(Builder $query, ?Carbon $at = null): Builder
    {
        $at = ($at ?: $this->now())->copy();

        $this->whereSaleTypeIn($query, self::FLASH_TYPES);

        if (Schema::hasColumn('products', 'flash_start_at')) {
            $query->where(function (Builder $window) use ($at) {
                $window->whereNull('flash_start_at')
                    ->orWhere('flash_start_at', '<=', $at);
            });
        }

        if (Schema::hasColumn('products', 'flash_end')) {
            $query->whereNotNull('flash_end')
                ->where('flash_end', '>', $at);
        } else {
            // Sans échéance, on ne peut pas garantir une vraie offre Flash temporaire.
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function applyFridayBlackFriday(Builder $query, ?Carbon $at = null): Builder
    {
        $at = ($at ?: $this->now())->copy();

        if (! $this->isBlackFridayDay($at)) {
            return $query->whereRaw('1 = 0');
        }

        $this->whereSaleTypeIn($query, self::BLACK_FRIDAY_TYPES);

        // Les dates restent optionnelles : NULL signifie participation récurrente chaque vendredi.
        if (Schema::hasColumn('products', 'bf_start')) {
            $query->where(function (Builder $window) use ($at) {
                $window->whereNull('bf_start')
                    ->orWhere('bf_start', '<=', $at);
            });
        }

        if (Schema::hasColumn('products', 'bf_end')) {
            $query->where(function (Builder $window) use ($at) {
                $window->whereNull('bf_end')
                    ->orWhere('bf_end', '>=', $at);
            });
        }

        return $query;
    }

    public function isFlashActive(Product $product, ?Carbon $at = null): bool
    {
        $at = ($at ?: $this->now())->copy();
        $type = self::normalizeSaleType($product->sale_type);

        if ($type !== 'vente flash') {
            return false;
        }

        if ($product->flash_start_at && $product->flash_start_at->gt($at)) {
            return false;
        }

        return (bool) ($product->flash_end && $product->flash_end->gt($at));
    }

    public function isBlackFridayActive(Product $product, ?Carbon $at = null): bool
    {
        $at = ($at ?: $this->now())->copy();
        $type = self::normalizeSaleType($product->sale_type);

        if ($type !== 'black friday' || ! $this->isBlackFridayDay($at)) {
            return false;
        }

        if ($product->bf_start && $product->bf_start->gt($at)) {
            return false;
        }

        if ($product->bf_end && $product->bf_end->lt($at)) {
            return false;
        }

        return true;
    }

    /**
     * Promotions classiques actives uniquement. Les campagnes Flash et
     * Black Friday ont leurs propres fenêtres et ne sont pas mélangées ici.
     */
    public function applyActivePromotion(Builder $query, ?Carbon $at = null): Builder
    {
        $at = ($at ?: $this->now())->copy();

        if (! Schema::hasColumn('products', 'promo_price')) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereNotNull('promo_price')
            ->where('promo_price', '>', 0)
            ->whereColumn('promo_price', '<', 'price');

        if (Schema::hasColumn('products', 'sale_type')) {
            $excluded = array_merge(self::FLASH_TYPES, self::BLACK_FRIDAY_TYPES);
            $query->where(function (Builder $types) use ($excluded) {
                $types->whereNull('sale_type')
                    ->orWhere('sale_type', '')
                    ->orWhereNotIn(\Illuminate\Support\Facades\DB::raw('LOWER(sale_type)'), $excluded);
            });
        }

        if (Schema::hasColumn('products', 'promo_end')) {
            $query->where(function (Builder $window) use ($at) {
                $window->whereNull('promo_end')
                    ->orWhere('promo_end', '>', $at);
            });
        }

        return $query;
    }

    public function isPromotionActive(Product $product, ?Carbon $at = null): bool
    {
        $at = ($at ?: $this->now())->copy();

        $type = self::normalizeSaleType($product->sale_type);
        if (in_array($type, ['vente flash', 'black friday'], true)) {
            return false;
        }

        $price = (float) ($product->price ?? 0);
        $promo = (float) ($product->promo_price ?? 0);

        if ($promo <= 0 || $promo >= $price) {
            return false;
        }

        return ! $product->promo_end || $product->promo_end->gt($at);
    }


    public function applyFeatured(Builder $query, ?Carbon $at = null): Builder
    {
        $at = ($at ?: $this->now())->copy();

        $query->where(function (Builder $boosts) use ($at) {
            $hasCondition = false;

            if (Schema::hasColumn('products', 'is_boosted')) {
                $boosts->where(function (Builder $paid) use ($at) {
                    $paid->where('is_boosted', 1);

                    if (Schema::hasColumn('products', 'boost_start_at')) {
                        $paid->where(function (Builder $window) use ($at) {
                            $window->whereNull('boost_start_at')
                                ->orWhere('boost_start_at', '<=', $at);
                        });
                    }

                    if (Schema::hasColumn('products', 'boost_end_at')) {
                        $paid->where(function (Builder $window) use ($at) {
                            $window->whereNull('boost_end_at')
                                ->orWhere('boost_end_at', '>=', $at);
                        });
                    }
                });
                $hasCondition = true;
            }

            if (Schema::hasColumn('products', 'free_boosted_by_ovanie')) {
                $method = $hasCondition ? 'orWhere' : 'where';

                $boosts->{$method}(function (Builder $free) use ($at) {
                    $free->where('free_boosted_by_ovanie', 1);

                    if (Schema::hasColumn('products', 'free_boost_start_at')) {
                        $free->where(function (Builder $window) use ($at) {
                            $window->whereNull('free_boost_start_at')
                                ->orWhere('free_boost_start_at', '<=', $at);
                        });
                    }

                    if (Schema::hasColumn('products', 'free_boost_end_at')) {
                        $free->where(function (Builder $window) use ($at) {
                            $window->whereNull('free_boost_end_at')
                                ->orWhere('free_boost_end_at', '>=', $at);
                        });
                    }
                });
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $boosts->whereRaw('1 = 0');
            }
        });

        return $this->excludeCommercialCampaigns($query, $at);
    }

    public function excludeCommercialCampaigns(Builder $query, ?Carbon $at = null): Builder
    {
        $at = ($at ?: $this->now())->copy();

        if (Schema::hasColumn('products', 'sale_type')) {
            $excludedTypes = array_merge(
                self::FLASH_TYPES,
                self::BLACK_FRIDAY_TYPES,
                ['promo', 'promotion']
            );

            $query->where(function (Builder $types) use ($excludedTypes) {
                $types->whereNull('sale_type')
                    ->orWhere('sale_type', '')
                    ->orWhereNotIn(\Illuminate\Support\Facades\DB::raw('LOWER(sale_type)'), $excludedTypes);
            });
        }

        if (Schema::hasColumn('products', 'promo_price')) {
            $query->where(function (Builder $promo) use ($at) {
                $promo->whereNull('promo_price')
                    ->orWhere('promo_price', '<=', 0)
                    ->orWhereColumn('promo_price', '>=', 'price');

                if (Schema::hasColumn('products', 'promo_end')) {
                    $promo->orWhere(function (Builder $expired) use ($at) {
                        $expired->whereNotNull('promo_end')
                            ->where('promo_end', '<=', $at);
                    });
                }
            });
        }

        return $query;
    }

    public static function normalizeSaleType(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        if (in_array($value, ['flash', 'flash sale', 'vente flash'], true)) {
            return 'vente flash';
        }

        if ($value === 'black friday') {
            return 'black friday';
        }

        if (in_array($value, ['promo', 'promotion'], true)) {
            return 'promotion';
        }

        return $value === '' ? 'normal' : $value;
    }

    private function whereSaleTypeIn(Builder $query, array $types): void
    {
        $query->where(function (Builder $saleTypes) use ($types) {
            foreach ($types as $index => $type) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $saleTypes->{$method}('LOWER(sale_type) = ?', [$type]);
            }
        });
    }
}
