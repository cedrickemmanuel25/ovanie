<?php

namespace App\Services;

use App\Models\Product;

class AiProductDescriptionService
{
    /**
     * Génère une description structurée BTP sans fournisseur IA externe.
     */
    public function generate(Product $product, array $context = []): array
    {
        $name = $product->name ?? 'Produit BTP';
        $brand = $product->brand ?? $context['brand'] ?? null;
        $unit = $product->unit_label ?? $product->unit ?? $context['unit'] ?? 'unité';
        $usage = $product->usage_area ?? $context['usage_area'] ?? 'travaux de construction et de finition';
        $packaging = $product->packaging ?? $context['packaging'] ?? null;

        $parts = [];
        $parts[] = $name . ' est un produit adapté aux ' . $usage . '.';

        if ($brand) {
            $parts[] = 'Marque : ' . $brand . '.';
        }

        if ($packaging) {
            $parts[] = 'Conditionnement : ' . $packaging . '.';
        }

        $parts[] = 'Unité de vente : ' . $unit . '.';
        $parts[] = 'Idéal pour les particuliers, artisans et entreprises BTP recherchant un achat fiable avec livraison organisée.';

        return [
            'title' => $name,
            'short_description' => implode(' ', array_slice($parts, 0, 2)),
            'long_description' => implode("\n", $parts),
            'selling_points' => array_values(array_filter([
                $brand ? "Marque {$brand}" : null,
                $packaging ? "Conditionnement {$packaging}" : null,
                "Vendu par {$unit}",
                'Compatible avec devis et livraison Ovanie',
            ])),
        ];
    }

    public function suggestMissingFields(Product $product): array
    {
        $fields = [
            'brand' => 'Ajouter la marque',
            'origin_country' => 'Ajouter le pays d’origine',
            'usage_area' => 'Préciser l’usage recommandé',
            'packaging' => 'Ajouter le conditionnement',
            'unit' => 'Définir l’unité de vente',
            'technical_sheet_path' => 'Ajouter une fiche technique PDF',
            'weight_kg' => 'Ajouter le poids pour la livraison',
            'volume_m3' => 'Ajouter le volume pour la livraison',
        ];

        return collect($fields)
            ->filter(fn ($label, $field) => empty($product->{$field}))
            ->values()
            ->all();
    }
}
