<?php

namespace App\Services;

use App\Models\Product;
use Throwable;

class ProductCalculatorService
{
    private const CATEGORIES = ['ciment', 'sable', 'gravier', 'carrelage', 'peinture'];


    public function profile(Product $product): array
    {
        $unit = (string) ($product->display_unit ?? $product->unit_label ?? $product->unit ?? 'unité');
        $normalizedUnit = mb_strtolower(trim($unit));
        $normalizedUnit = strtr($normalizedUnit, ['²' => '2', '³' => '3']);

        $lengthM = $product->length_cm ? round((float) $product->length_cm / 100, 4) : 0.0;
        $widthM = $product->width_cm ? round((float) $product->width_cm / 100, 4) : 0.0;
        $heightM = $product->height_cm ? round((float) $product->height_cm / 100, 4) : 0.0;
        $unitSurfaceM2 = (float) ($product->coverage_per_unit_m2 ?: (($lengthM > 0 && $widthM > 0) ? $lengthM * $widthM : 0));
        $unitVolumeM3 = (float) ($product->volume_m3 ?: (($lengthM > 0 && $widthM > 0 && $heightM > 0) ? $lengthM * $widthM * $heightM : 0));
        $unitWeightKg = (float) ($product->weight_kg ?: ($product->weight ?? 0));
        $densityKgM3 = ($unitWeightKg > 0 && $unitVolumeM3 > 0)
            ? round($unitWeightKg / $unitVolumeM3, 2)
            : 0.0;

        $defaultType = match (true) {
            str_contains($normalizedUnit, 'm2') || str_contains($normalizedUnit, 'surface') => 'surface',
            str_contains($normalizedUnit, 'm3') || str_contains($normalizedUnit, 'tonne') || str_contains($normalizedUnit, 'kg') || str_contains($normalizedUnit, 'sac') => 'volume',
            str_contains($normalizedUnit, 'ml') || str_contains($normalizedUnit, 'linéaire') || str_contains($normalizedUnit, 'lineaire') || str_contains($normalizedUnit, 'barre') || str_contains($normalizedUnit, 'rouleau') => 'linear',
            default => 'piece',
        };

        $availabilityStatus = (string) ($product->availability_status ?: ((int) ($product->stock ?? 0) > 0 ? 'in_stock' : 'out_of_stock'));

        return [
            'unit' => $unit,
            'default_type' => $defaultType,
            'price' => (float) ($product->final_price ?? $product->price ?? 0),
            'stock' => max(0, (int) ($product->stock ?? 0)),
            'min_qty' => max(1, (int) ($product->min_order_quantity ?? 1)),
            'availability_status' => $availabilityStatus,
            'coverage_per_unit_m2' => round($unitSurfaceM2, 4),
            'unit_volume_m3' => round($unitVolumeM3, 4),
            'unit_weight_kg' => round($unitWeightKg, 3),
            'density_kg_m3' => round($densityKgM3, 2),
            'unit_length_m' => $lengthM,
        ];
    }

    public function estimate(array $data): array
    {
        $category = strtolower((string) ($data['category'] ?? 'ciment'));
        $category = in_array($category, self::CATEGORIES, true) ? $category : 'ciment';

        $surface = $this->surface($data);
        $volume = $this->volume($data);
        $layers = max((int) ($data['layers'] ?? 1), 1);
        $wasteMargin = max((float) ($data['waste_margin'] ?? 10), 0);
        $unitBudget = $data['unit_budget'] ?? null;

        [$quantity, $unit, $basis] = $this->quantityFor($category, $surface, $volume, $layers);
        $quantityWithWaste = $quantity * (1 + ($wasteMargin / 100));
        $roundedQuantity = $this->roundQuantity($quantityWithWaste, $unit);
        $estimatedBudget = is_numeric($unitBudget) ? round($roundedQuantity * (float) $unitBudget, 2) : null;

        return [
            'category' => $category,
            'quantity' => $roundedQuantity,
            'unit' => $unit,
            'basis' => $basis,
            'surface_m2' => round($surface, 2),
            'volume_m3' => round($volume, 3),
            'waste_margin' => $wasteMargin,
            'budget_estimate' => $estimatedBudget,
            'recommended_products' => $this->recommendedProducts($category, $unitBudget),
        ];
    }

    public function categories(): array
    {
        return self::CATEGORIES;
    }

    private function surface(array $data): float
    {
        if (isset($data['surface']) && is_numeric($data['surface']) && (float) $data['surface'] > 0) {
            return (float) $data['surface'];
        }

        $length = max((float) ($data['length'] ?? 0), 0);
        $width = max((float) ($data['width'] ?? 0), 0);

        return $length * $width;
    }

    private function volume(array $data): float
    {
        $length = max((float) ($data['length'] ?? 0), 0);
        $width = max((float) ($data['width'] ?? 0), 0);
        $height = max((float) ($data['height'] ?? 0), 0);
        $thickness = max((float) ($data['thickness'] ?? 0), 0);

        if ($length > 0 && $width > 0 && $height > 0) {
            return $length * $width * $height;
        }

        if ($length > 0 && $width > 0 && $thickness > 0) {
            return $length * $width * $thickness;
        }

        return 0;
    }

    private function quantityFor(string $category, float $surface, float $volume, int $layers): array
    {
        return match ($category) {
            'ciment' => [max($volume * 7, 1), 'sac(s) de 50 kg', 'Dosage indicatif de 7 sacs par m3'],
            'sable' => [max($volume, 0.1), 'm3', 'Volume chantier estime'],
            'gravier' => [max($volume, 0.1), 'm3', 'Volume chantier estime'],
            'carrelage' => [max($surface, 1), 'm2', 'Surface a couvrir'],
            'peinture' => [max(($surface * $layers) / 8, 1), 'litre(s)', 'Rendement indicatif de 8 m2 par litre'],
        };
    }

    private function roundQuantity(float $quantity, string $unit): float
    {
        if (str_contains($unit, 'sac')) {
            return (float) ceil($quantity);
        }

        return round($quantity, 2);
    }

    private function recommendedProducts(string $category, mixed $unitBudget): array
    {
        try {
            return Product::query()
                ->with('category')
                ->where(function ($query) use ($category) {
                    $query->where('name', 'like', "%{$category}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($category) {
                            $categoryQuery->where('name', 'like', "%{$category}%")
                                ->orWhere('slug', 'like', "%{$category}%");
                        });
                })
                ->when(is_numeric($unitBudget), function ($query) use ($unitBudget) {
                    $query->where('price', '<=', (float) $unitBudget);
                })
                ->where(function ($query) {
                    $query->where('is_active', true)
                        ->orWhere('status', 'actif')
                        ->orWhere('status', 'active');
                })
                ->orderBy('price')
                ->limit(6)
                ->get()
                ->map(fn(Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (float) $product->price,
                    'image' => $product->main_image_url,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
