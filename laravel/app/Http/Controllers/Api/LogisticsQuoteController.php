<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarrierRateCard;
use App\Services\CarrierComparisonService;
use Illuminate\Http\Request;

class LogisticsQuoteController extends Controller
{
    public function __construct(private CarrierComparisonService $comparison)
    {
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'origin_city' => ['nullable', 'string', 'max:120'],
            'destination_city' => ['nullable', 'string', 'max:120'],
            'zone' => ['nullable', 'string', 'max:120'],
            'distance_km' => ['required', 'numeric', 'min:0'],
            'weight_ton' => ['required', 'numeric', 'min:0'],
            'is_urgent' => ['nullable', 'boolean'],
            'is_fragile' => ['nullable', 'boolean'],
            'requires_unloading' => ['nullable', 'boolean'],
        ]);

        $rateCards = CarrierRateCard::query()
            ->with('carrier')
            ->where('is_active', true)
            ->whereHas('carrier', fn($query) => $query->where('is_active', true))
            ->when($payload['zone'] ?? null, function ($query, $zone) {
                $query->where(function ($zoneQuery) use ($zone) {
                    $zoneQuery->where('zone', $zone)->orWhereNull('zone');
                });
            })
            ->get();

        if ($rateCards->isEmpty()) {
            return response()->json([
                'message' => 'Aucun transporteur actif disponible pour cette demande.',
                'quotes' => [],
            ], 404);
        }

        $result = $this->comparison->compare($rateCards, $payload);

        return response()->json([
            'cheapest_carrier' => $result['cheapest'],
            'fastest_carrier' => $result['fastest'],
            'recommended_carrier' => $result['recommended'],
            'estimated_delay_hours' => $result['recommended']['estimated_delay_hours'] ?? null,
            'final_price' => $result['recommended']['final_price'] ?? null,
            'quotes' => $result['quotes'],
        ]);
    }
}
