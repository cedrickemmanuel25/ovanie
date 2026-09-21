<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class AiCalculatorAssistantService
{
    /**
     * Prépare une estimation chantier générique, sans IA externe.
     */
    public function estimateProject(array $payload): array
    {
        $surface = (float) ($payload['surface'] ?? (($payload['length'] ?? 0) * ($payload['width'] ?? 0)));
        $height = (float) ($payload['height'] ?? 0);
        $wasteMargin = (float) ($payload['waste_margin'] ?? config('ai.calculator.default_waste_margin', 10));
        $projectType = $payload['project_type'] ?? 'chantier';

        $base = max($surface, 0);
        $marginFactor = 1 + ($wasteMargin / 100);

        return [
            'project_type' => $projectType,
            'surface_m2' => round($surface, 2),
            'height_m' => round($height, 2),
            'waste_margin_percent' => $wasteMargin,
            'estimated_needs' => [
                'cement_bags' => (int) ceil(($base * 1.2) * $marginFactor),
                'sand_m3' => round(($base * 0.08) * $marginFactor, 2),
                'gravel_m3' => round(($base * 0.08) * $marginFactor, 2),
                'paint_liters' => round(($base * 0.18) * $marginFactor, 2),
                'tiles_m2' => round($base * $marginFactor, 2),
            ],
            'notice' => 'Estimation indicative à ajuster selon plans, dosage, qualité du sol et avis d’un professionnel BTP.',
        ];
    }

    public function suggestProductsForEstimate(array $estimate, int $limit = 10): Collection
    {
        $keywords = ['ciment', 'sable', 'gravier', 'peinture', 'carrelage'];

        return Product::query()
            ->with(['category', 'shop'])
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                }
            })
            ->limit($limit)
            ->get();
    }

    public function answerClientQuestion(string $question, array $context = []): array
    {
        return [
            'question' => $question,
            'answer' => 'Je peux vous aider a estimer les materiaux, comparer les produits et preparer une estimation OVANIE. Pour une reponse precise, indiquez la surface, la ville, le type de chantier et le budget.',
            'suggested_next_steps' => [
                'Calculer les quantités nécessaires',
                'Voir les produits recommandés',
                'Demander un devis fournisseur',
                'Comparer les options de livraison',
            ],
            'context' => $context,
        ];
    }
}
