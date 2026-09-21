<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Arr;

class AiPriceAnalysisService
{
    /**
     * Analyse un prix par rapport aux produits similaires.
     */
    public function analyzeProductPrice(Product $product): array
    {
        $price = (float) ($product->price ?? 0);
        $threshold = (float) config('ai.price_analysis.anomaly_threshold_percent', 35);

        $similarPrices = Product::query()
            ->where('id', '!=', $product->id)
            ->when($product->category_id ?? null, fn ($query) => $query->where('category_id', $product->category_id))
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->limit(30)
            ->pluck('price')
            ->map(fn ($value) => (float) $value)
            ->values()
            ->all();

        if (count($similarPrices) < (int) config('ai.price_analysis.min_products_for_comparison', 3)) {
            return [
                'status' => 'insufficient_data',
                'message' => 'Pas assez de produits similaires pour comparer le prix.',
                'price' => $price,
            ];
        }

        $average = array_sum($similarPrices) / count($similarPrices);
        $differencePercent = $average > 0 ? (($price - $average) / $average) * 100 : 0;
        $isAnomaly = abs($differencePercent) >= $threshold;

        return [
            'status' => $isAnomaly ? 'anomaly_detected' : 'normal',
            'price' => round($price, 2),
            'market_average' => round($average, 2),
            'difference_percent' => round($differencePercent, 2),
            'threshold_percent' => $threshold,
            'recommendation' => $this->recommendation($differencePercent, $threshold),
        ];
    }

    public function estimateBudget(array $needs, array $unitPrices = []): array
    {
        $lines = [];
        $total = 0;

        foreach ($needs as $key => $quantity) {
            $unitPrice = (float) Arr::get($unitPrices, $key, 0);
            $lineTotal = (float) $quantity * $unitPrice;
            $total += $lineTotal;

            $lines[] = [
                'item' => $key,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => round($lineTotal, 2),
            ];
        }

        return [
            'currency' => config('ai.calculator.currency', 'FCFA'),
            'lines' => $lines,
            'estimated_total' => round($total, 2),
        ];
    }

    private function recommendation(float $differencePercent, float $threshold): string
    {
        if ($differencePercent >= $threshold) {
            return 'Prix supérieur au marché : vérifier la justification, la qualité, la marque ou le conditionnement.';
        }

        if ($differencePercent <= -$threshold) {
            return 'Prix inférieur au marché : vérifier le stock, l’authenticité et les conditions de livraison.';
        }

        return 'Prix cohérent avec les produits similaires.';
    }
}
