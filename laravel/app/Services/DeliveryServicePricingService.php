<?php

namespace App\Services;

use App\Models\DeliveryService;
use App\Models\DeliveryServiceRate;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use App\Services\Geo\AbidjanLocationResolver;

class DeliveryServicePricingService
{
    public function __construct(
        private readonly OvanieDeliveryPriceCalculator $calculator,
        private readonly AbidjanLocationResolver $locations
    ) {}

    public function price(DeliveryService $service, Collection $items, ?Request $request = null): float
    {
        if ((bool) data_get($service->meta, 'quote_required', false)) {
            return 0.0;
        }

        $weightKg = $this->weightKg($items);
        $volumeM3 = $this->volumeM3($items);
        $hasFragile = $items->contains(fn ($item) => (bool) ($item->product?->fragile ?? false));
        $requiresUnloading = $items->contains(fn ($item) => (bool) ($item->product?->requires_unloading ?? false));
        $isUrgent = $service->estimated_hours <= 24;

        $rate = $this->bestRate($service, $request);

        if (! $rate) {
            return 0.0;
        }

        $fee = (float) $rate->base_fee
            + ((float) $rate->price_per_kg * $weightKg)
            + ((float) $rate->price_per_m3 * $volumeM3);

        if ($hasFragile) {
            $fee += (float) $rate->fragile_fee;
        }

        if ($requiresUnloading) {
            $fee += (float) $rate->unloading_fee;
        }

        if ($isUrgent) {
            $fee += (float) $rate->urgent_fee;
        }

        if ($rate->min_fee !== null) {
            $fee = max($fee, (float) $rate->min_fee);
        }

        if ($rate->max_fee !== null && (float) $rate->max_fee > 0) {
            $fee = min($fee, (float) $rate->max_fee);
        }

        return round($fee, 0);
    }

    public function bestRate(DeliveryService $service, ?Request $request = null): ?DeliveryServiceRate
    {
        $deliveryZone = $request?->input('delivery_zone');
        $city = $request?->input('delivery_zone') === 'interieur'
            ? $request?->input('delivery_city')
            : 'Abidjan';
        $commune = $request?->input('delivery_zone') === 'abidjan'
            ? $request?->input('delivery_commune')
            : null;

        $query = $service->rates()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
            });

        $rates = $query->get();

        if ($rates->isEmpty()) {
            return null;
        }

        return $rates
            ->sortByDesc(function (DeliveryServiceRate $rate) use ($deliveryZone, $city, $commune) {
                $score = 0;

                if ($rate->delivery_zone && $deliveryZone && $this->normalizeLocation($rate->delivery_zone) === $this->normalizeLocation($deliveryZone)) {
                    $score += 10;
                }

                if ($rate->city && $city && $this->normalizeLocation($rate->city) === $this->normalizeLocation($city)) {
                    $score += 20;
                }

                if ($rate->commune && $commune && $this->normalizeLocation($rate->commune) === $this->normalizeLocation($commune)) {
                    $score += 30;
                }

                if (! $rate->delivery_zone && ! $rate->city && ! $rate->commune) {
                    $score += 1;
                }

                return $score;
            })
            ->first();
    }

    public function isServiceAvailableForItems(DeliveryService $service, Collection $items): bool
    {
        $weightKg = $this->weightKg($items);
        $volumeM3 = $this->volumeM3($items);
        $maxLength = (float) $items->max(fn ($item) => (float) ($item->product?->length_cm ?? 0));
        $maxWidth = (float) $items->max(fn ($item) => (float) ($item->product?->width_cm ?? 0));
        $maxHeight = (float) $items->max(fn ($item) => (float) ($item->product?->height_cm ?? 0));

        if ($service->max_weight_kg && $weightKg > (float) $service->max_weight_kg) {
            return false;
        }

        if ($service->max_volume_m3 && $volumeM3 > (float) $service->max_volume_m3) {
            return false;
        }

        if ($service->max_length_cm && $maxLength > (float) $service->max_length_cm) {
            return false;
        }

        if ($service->max_width_cm && $maxWidth > (float) $service->max_width_cm) {
            return false;
        }

        if ($service->max_height_cm && $maxHeight > (float) $service->max_height_cm) {
            return false;
        }

        return true;
    }

    private function normalizeLocation(mixed $value): string
    {
        return $this->locations->communeSlug(is_scalar($value) ? (string) $value : null);
    }

    private function weightKg(Collection $items): float
    {
        return $this->calculator->weightKg($items);
    }

    private function volumeM3(Collection $items): float
    {
        return $this->calculator->volumeM3($items);
    }
}
