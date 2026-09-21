<?php

namespace App\Services\SupportAi;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SupportCatalogSearchService
{
    /**
     * Recherche uniquement dans les produits actifs/publics OVANIE.
     * Aucune identité de vendeur n'est exposée au Support IA.
     *
     * @return array{query:string,terms:list<string>,categories:list<string>,products:list<array<string,mixed>>}
     */
    public function search(string $text, array $memory = []): array
    {
        $queryText = trim($this->buildQueryText($text, $memory));
        $terms = $this->terms($queryText);
        $constraints = $this->constraints($queryText, $memory);

        if ($terms === []) {
            return [
                'query' => $queryText,
                'terms' => [],
                'categories' => $this->officialCategories(),
                'products' => [],
            ];
        }

        $products = Product::query()
            ->active()
            ->notArchived()
            ->where(function (Builder $builder) use ($terms): void {
                foreach ($terms as $term) {
                    $builder->orWhere(function (Builder $inner) use ($term): void {
                        $like = '%'.$term.'%';
                        $inner->where('name', 'like', $like)
                            ->orWhere('brand', 'like', $like)
                            ->orWhere('short_description', 'like', $like)
                            ->orWhere('description', 'like', $like)
                            ->orWhere('type', 'like', $like)
                            ->orWhere('unit_label', 'like', $like)
                            ->orWhere('packaging', 'like', $like);
                    });
                }
            })
            ->with('category:id,name')
            ->limit(40)
            ->get([
                'id', 'category_id', 'name', 'slug', 'brand', 'price', 'promo_price',
                'unit', 'unit_label', 'packaging', 'stock', 'availability_status',
                'weight_kg', 'min_order_quantity', 'short_description',
            ]);

        $ranked = $products
            ->map(function (Product $product) use ($terms, $constraints): array {
                $haystack = $this->normalize(implode(' ', array_filter([
                    $product->name,
                    $product->brand,
                    $product->short_description,
                    $product->category?->name,
                    $product->unit,
                    $product->unit_label,
                    $product->packaging,
                ])));

                $score = 0;
                foreach ($terms as $term) {
                    if (str_contains($haystack, $term)) {
                        $score += 2;
                    }
                }

                $weight = $product->weight_kg !== null ? (float) $product->weight_kg : null;
                $weightMatch = false;
                if ($constraints['weight_kg'] !== null && $weight !== null) {
                    $weightMatch = abs($weight - $constraints['weight_kg']) <= 0.25;
                    $score += $weightMatch ? 8 : -2;
                }

                $packagingMatch = false;
                if ($constraints['packaging'] !== null) {
                    $packagingText = $this->normalize((string) $product->packaging.' '.(string) $product->unit_label);
                    $packagingMatch = str_contains($packagingText, $constraints['packaging']);
                    $score += $packagingMatch ? 4 : 0;
                }

                $brandMatch = false;
                if ($constraints['brand'] !== null) {
                    $brandMatch = str_contains($this->normalize((string) $product->brand), $constraints['brand']);
                    $score += $brandMatch ? 5 : 0;
                }

                // Même prix public que le catalogue Web et le panier : prix vendeur
                // + commission OVANIE, avec la promotion active éventuelle.
                $basePrice = (float) $product->normal_public_price;
                $effectivePrice = (float) $product->final_price;
                $promoPrice = (bool) $product->is_on_promo ? $effectivePrice : null;

                return [
                    '_score' => $score,
                    '_weight_match' => $weightMatch,
                    '_packaging_match' => $packagingMatch,
                    '_brand_match' => $brandMatch,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'category' => $product->category?->name,
                    'price' => $basePrice,
                    'promo_price' => $promoPrice,
                    'effective_price' => $effectivePrice,
                    'unit' => $product->unit,
                    'unit_label' => $product->unit_label,
                    'packaging' => $product->packaging,
                    'weight_kg' => $weight,
                    'min_order_quantity' => $product->min_order_quantity !== null
                        ? (int) $product->min_order_quantity
                        : null,
                    'stock' => $product->stock !== null ? (int) $product->stock : null,
                    'availability_status' => $product->availability_status,
                ];
            })
            ->filter(fn (array $item) => (int) $item['_score'] > 0);

        // Si le catalogue contient des produits correspondant exactement au poids demandé,
        // on élimine les autres poids au lieu de laisser Claude choisir arbitrairement.
        if ($constraints['weight_kg'] !== null && $ranked->contains(fn (array $item) => $item['_weight_match'])) {
            $ranked = $ranked->filter(fn (array $item) => $item['_weight_match']);
        }

        if ($constraints['packaging'] !== null && $ranked->contains(fn (array $item) => $item['_packaging_match'])) {
            $ranked = $ranked->filter(fn (array $item) => $item['_packaging_match']);
        }

        if ($constraints['brand'] !== null && $ranked->contains(fn (array $item) => $item['_brand_match'])) {
            $ranked = $ranked->filter(fn (array $item) => $item['_brand_match']);
        }

        $ranked = $ranked
            ->sort(function (array $a, array $b): int {
                $scoreCompare = ((int) $b['_score']) <=> ((int) $a['_score']);
                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }

                $aPrice = $a['effective_price'] ?? PHP_FLOAT_MAX;
                $bPrice = $b['effective_price'] ?? PHP_FLOAT_MAX;
                return $aPrice <=> $bPrice;
            })
            ->take(8)
            ->map(function (array $item): array {
                unset($item['_score'], $item['_weight_match'], $item['_packaging_match'], $item['_brand_match']);
                return $item;
            })
            ->values()
            ->all();

        return [
            'query' => $queryText,
            'terms' => $terms,
            'categories' => $this->officialCategories(),
            'products' => $ranked,
        ];
    }

    /** @return list<string> */
    private function officialCategories(): array
    {
        return Category::query()
            ->active()
            ->roots()
            ->ordered()
            ->limit(12)
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn (string $name) => trim($name))
            ->values()
            ->all();
    }

    private function buildQueryText(string $text, array $memory): string
    {
        $facts = (array) data_get($memory, 'structured.collected_facts', []);

        return trim(implode(' ', array_filter([
            $text,
            $facts['product'] ?? $facts['product_name'] ?? null,
            $facts['brand'] ?? null,
            $facts['category'] ?? null,
            $facts['weight'] ?? $facts['weight_kg'] ?? null,
            $facts['packaging'] ?? null,
        ])));
    }

    /** @return array{weight_kg:?float,packaging:?string,brand:?string} */
    private function constraints(string $text, array $memory): array
    {
        $facts = (array) data_get($memory, 'structured.collected_facts', []);
        $normalized = $this->normalize($text);

        $weight = null;
        $weightRaw = (string) ($facts['weight_kg'] ?? $facts['weight'] ?? '');
        if ($weightRaw !== '' && preg_match('/(\d+(?:[\.,]\d+)?)/', $weightRaw, $m)) {
            $weight = (float) str_replace(',', '.', $m[1]);
        } elseif (preg_match('/\b(\d+(?:[\.,]\d+)?)\s*(?:kg|kilo|kilos)\b/i', $text, $m)) {
            $weight = (float) str_replace(',', '.', $m[1]);
        }

        $packaging = null;
        $packagingRaw = $this->normalize((string) ($facts['packaging'] ?? ''));
        foreach (['sac', 'vrac', 'palette', 'carton', 'unite'] as $candidate) {
            if ($packagingRaw === $candidate || str_contains($normalized, $candidate)) {
                $packaging = $candidate;
                break;
            }
        }

        $brand = trim($this->normalize((string) ($facts['brand'] ?? ''))) ?: null;

        return [
            'weight_kg' => $weight,
            'packaging' => $packaging,
            'brand' => $brand,
        ];
    }

    /** @return list<string> */
    private function terms(string $text): array
    {
        $normalized = $this->normalize($text);
        $stop = [
            'ovanie', 'bonjour', 'bonsoir', 'salut', 'merci', 'avec', 'pour', 'dans',
            'vous', 'votre', 'comment', 'quoi', 'quel', 'quelle', 'faire', 'avoir',
            'etre', 'une', 'des', 'les', 'du', 'de', 'la', 'le', 'un', 'mes',
            'mon', 'ma', 'je', 'veux', 'voudrais', 'souhaite', 'cherche', 'chercher',
            'trouver', 'trouve', 'besoin', 'aide', 'prix', 'moins', 'cher', 'chers',
            'meilleur', 'meilleure', 'livraison', 'livre', 'livrer', 'cocody', 'riviera',
            'abidjan', 'catalogue', 'acheter', 'achat', 'commande', 'unique', 'regulier',
            'reguliere', 'kg', 'kilo', 'kilos', 'sac', 'sacs', 'vrac', 'quantite',
            'combien', 'disponible', 'disponibilite',
        ];

        return collect(preg_split('/\s+/u', $normalized) ?: [])
            ->map(fn ($term) => trim((string) $term))
            ->filter(fn ($term) => mb_strlen($term) >= 3)
            ->reject(fn ($term) => in_array($term, $stop, true))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
