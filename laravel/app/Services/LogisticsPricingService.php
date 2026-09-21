<?php

namespace App\Services;

use App\Models\CarrierRateCard;

class LogisticsPricingService
{
    public function calculate(CarrierRateCard|array $rateCard, array $payload): array
    {
        $basePrice = $this->value($rateCard, 'base_price');
        $pricePerKm = $this->value($rateCard, 'price_per_km');
        $pricePerTon = $this->value($rateCard, 'price_per_ton');
        $zoneSurcharge = $this->value($rateCard, 'zone_surcharge');

        $urgencySurcharge = $this->bool($payload, 'is_urgent')
            ? $this->value($rateCard, 'urgency_surcharge')
            : 0;
        $fragileSurcharge = $this->bool($payload, 'is_fragile')
            ? $this->value($rateCard, 'fragile_surcharge')
            : 0;
        $unloadingSurcharge = $this->bool($payload, 'requires_unloading')
            ? $this->value($rateCard, 'unloading_surcharge')
            : 0;

        $distance = max((float) ($payload['distance_km'] ?? 0), 0);
        $weight = max((float) ($payload['weight_ton'] ?? 0), 0);

        $finalPrice = $basePrice
            + ($distance * $pricePerKm)
            + ($weight * $pricePerTon)
            + $zoneSurcharge
            + $urgencySurcharge
            + $fragileSurcharge
            + $unloadingSurcharge;

        return [
            'base_price' => round($basePrice, 2),
            'distance_amount' => round($distance * $pricePerKm, 2),
            'weight_amount' => round($weight * $pricePerTon, 2),
            'zone_surcharge' => round($zoneSurcharge, 2),
            'urgency_surcharge' => round($urgencySurcharge, 2),
            'fragile_surcharge' => round($fragileSurcharge, 2),
            'unloading_surcharge' => round($unloadingSurcharge, 2),
            'final_price' => round($finalPrice, 2),
        ];
    }

    private function value(CarrierRateCard|array $rateCard, string $key): float
    {
        return (float) (is_array($rateCard) ? ($rateCard[$key] ?? 0) : ($rateCard->{$key} ?? 0));
    }

    private function bool(array $payload, string $key): bool
    {
        return filter_var($payload[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
