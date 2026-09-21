<?php

namespace App\Services;

use App\Models\CarrierRateCard;

class CarrierComparisonService
{
    public function __construct(private LogisticsPricingService $pricing)
    {
    }

    public function compare(iterable $rateCards, array $payload): array
    {
        $quotes = collect($rateCards)->map(function (CarrierRateCard $rateCard) use ($payload) {
            $price = $this->pricing->calculate($rateCard, $payload);

            return [
                'carrier_id' => $rateCard->carrier_id,
                'carrier_name' => $rateCard->carrier?->name,
                'rate_card_id' => $rateCard->id,
                'zone' => $rateCard->zone,
                'estimated_delay_hours' => (int) $rateCard->estimated_delay_hours,
                'price' => $price,
                'final_price' => $price['final_price'],
            ];
        })->values();

        $cheapest = $quotes->sortBy('final_price')->first();
        $fastest = $quotes->sortBy('estimated_delay_hours')->first();
        $recommended = $quotes
            ->sortBy(fn($quote) => ($quote['final_price'] * 0.7) + ($quote['estimated_delay_hours'] * 100))
            ->first();

        return [
            'quotes' => $quotes->all(),
            'cheapest' => $cheapest,
            'fastest' => $fastest,
            'recommended' => $recommended,
        ];
    }
}
