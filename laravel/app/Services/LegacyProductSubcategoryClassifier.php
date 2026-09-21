<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LegacyProductSubcategoryClassifier
{
    public function suggest(Product $product, Collection $children): array
    {
        $rules = (array) config('catalog_subcategory_rules.keywords', []);
        $minimumScore = (int) config('catalog_subcategory_rules.minimum_score', 60);
        $minimumMargin = (int) config('catalog_subcategory_rules.minimum_margin', 20);

        $fields = [
            'name' => $this->normalize((string) ($product->name ?? '')),
            'type' => $this->normalize((string) ($product->type ?? '')),
            'short_description' => $this->normalize((string) ($product->short_description ?? '')),
            'description' => $this->normalize((string) ($product->description ?? '')),
            'usage_area' => $this->normalize((string) ($product->usage_area ?? '')),
            'brand' => $this->normalize((string) ($product->brand ?? '')),
            'packaging' => $this->normalize((string) ($product->packaging ?? '')),
            'technical_details' => $this->normalize((string) ($product->technical_details ?? '')),
            'attributes' => $this->normalize($this->flattenAttributes($product->product_attributes ?? [])),
        ];

        $results = $children->map(function (Category $child) use ($fields, $rules) {
            $slug = Str::slug((string) ($child->slug ?: $child->name));
            $name = $this->normalize((string) $child->name);
            $phrases = collect($rules[$slug] ?? [])
                ->push($name)
                ->map(fn ($phrase) => $this->normalize((string) $phrase))
                ->filter(fn ($phrase) => mb_strlen($phrase) >= 3)
                ->unique()
                ->values();

            $score = 0;
            $reasons = [];

            foreach ($phrases as $phrase) {
                if ($phrase === '') {
                    continue;
                }

                if ($this->contains($fields['name'], $phrase) || $this->contains($fields['type'], $phrase)) {
                    $points = $phrase === $name ? 95 : 75;
                    $score += $points;
                    $reasons[] = "nom/type: {$phrase} (+{$points})";
                    continue;
                }

                if ($this->contains($fields['short_description'], $phrase) || $this->contains($fields['usage_area'], $phrase)) {
                    $score += 45;
                    $reasons[] = "description courte/usage: {$phrase} (+45)";
                    continue;
                }

                if (
                    $this->contains($fields['description'], $phrase)
                    || $this->contains($fields['packaging'], $phrase)
                    || $this->contains($fields['technical_details'], $phrase)
                    || $this->contains($fields['attributes'], $phrase)
                ) {
                    $score += 25;
                    $reasons[] = "description: {$phrase} (+25)";
                }
            }

            // Bonus léger sur les mots significatifs du nom de sous-catégorie.
            $tokens = collect(explode(' ', $name))
                ->map(fn ($token) => trim($token))
                ->filter(fn ($token) => mb_strlen($token) >= 5)
                ->reject(fn ($token) => in_array($token, ['materiaux', 'produits', 'equipements', 'accessoires', 'reconditionnes'], true))
                ->unique();

            foreach ($tokens as $token) {
                if ($this->contains($fields['name'], $token) || $this->contains($fields['type'], $token)) {
                    $score += 18;
                    $reasons[] = "mot categorie: {$token} (+18)";
                }
            }

            return [
                'category' => $child,
                'score' => $score,
                'reasons' => array_values(array_unique($reasons)),
            ];
        })
            ->sortByDesc('score')
            ->values();

        $best = $results->get(0);
        $second = $results->get(1);
        $bestScore = (int) data_get($best, 'score', 0);
        $secondScore = (int) data_get($second, 'score', 0);
        $margin = $bestScore - $secondScore;

        $confident = $best
            && $bestScore >= $minimumScore
            && $margin >= $minimumMargin;

        return [
            'category' => $confident ? $best['category'] : null,
            'best_category' => $best['category'] ?? null,
            'score' => $bestScore,
            'second_score' => $secondScore,
            'margin' => $margin,
            'confident' => (bool) $confident,
            'reasons' => $best['reasons'] ?? [],
        ];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replace(['&', '/', '_', '-'], ' ')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }


    private function flattenAttributes(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)
                ->flatMap(fn ($item, $key) => [(string) $key, $this->flattenAttributes($item)])
                ->filter()
                ->implode(' ');
        }

        if (is_object($value)) {
            return $this->flattenAttributes((array) $value);
        }

        return is_scalar($value) ? (string) $value : '';
    }
    private function contains(string $haystack, string $needle): bool
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        return str_contains(" {$haystack} ", " {$needle} ")
            || str_contains($haystack, $needle);
    }
}
