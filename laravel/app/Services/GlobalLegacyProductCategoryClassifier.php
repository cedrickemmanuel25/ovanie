<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GlobalLegacyProductCategoryClassifier
{
    public function suggest(Product $product, Collection $leafCategories): array
    {
        $minimumScore = (int) config('catalog_global_classifier.minimum_score', 110);
        $minimumMargin = (int) config('catalog_global_classifier.minimum_margin', 35);
        $currentParentBonus = (int) config('catalog_global_classifier.current_parent_bonus', 12);
        $fieldScores = (array) config('catalog_global_classifier.field_scores', []);
        $exclusive = (array) config('catalog_global_classifier.exclusive_keywords', []);
        $extra = (array) config('catalog_global_classifier.extra_keywords', []);
        $baseRules = (array) config('catalog_subcategory_rules.keywords', []);

        $fields = $this->productFields($product);
        $currentRoot = $product->category;
        $currentRootId = $currentRoot?->parent_id ? $currentRoot->parent_id : $currentRoot?->id;

        $candidates = $leafCategories
            ->filter(fn (Category $leaf) => $this->candidateAllowed($product, $leaf))
            ->map(function (Category $leaf) use (
                $fields,
                $currentRootId,
                $currentParentBonus,
                $fieldScores,
                $exclusive,
                $extra,
                $baseRules
            ): array {
                $slug = Str::slug((string) ($leaf->slug ?: $leaf->name));
                $categoryName = $this->normalize((string) $leaf->name);

                $phrases = collect($baseRules[$slug] ?? [])
                    ->merge($extra[$slug] ?? [])
                    ->push($categoryName)
                    ->map(fn ($value) => $this->normalize((string) $value))
                    ->filter(fn ($value) => mb_strlen($value) >= 2)
                    ->unique()
                    ->values();

                $exclusivePhrases = collect($exclusive[$slug] ?? [])
                    ->map(fn ($value) => $this->normalize((string) $value))
                    ->filter(fn ($value) => mb_strlen($value) >= 2)
                    ->unique()
                    ->values();

                $score = 0;
                $reasons = [];

                foreach ($fields as $field => $content) {
                    if ($content === '') {
                        continue;
                    }

                    $base = (int) ($fieldScores[$field] ?? 0);
                    if ($base <= 0) {
                        continue;
                    }

                    $matched = $phrases->filter(fn ($phrase) => $this->contains($content, $phrase))->values();
                    if ($matched->isNotEmpty()) {
                        $bestPhrase = $matched->sortByDesc(fn ($phrase) => mb_strlen($phrase))->first();
                        $points = $base;

                        if ($field === 'name' && $content === $bestPhrase) {
                            $points += 40;
                        }

                        $score += $points;
                        $reasons[] = "{$field}: {$bestPhrase} (+{$points})";
                    }

                    // Signal exclusif : bonus uniquement sur nom/type afin d'éviter
                    // qu'une description trop générale force un reclassement.
                    if (in_array($field, ['name', 'type'], true)) {
                        $exclusiveMatched = $exclusivePhrases
                            ->filter(fn ($phrase) => $this->contains($content, $phrase))
                            ->sortByDesc(fn ($phrase) => mb_strlen($phrase))
                            ->first();

                        if ($exclusiveMatched) {
                            $bonus = $field === 'name' ? 140 : 100;
                            $score += $bonus;
                            $reasons[] = "signal fort {$field}: {$exclusiveMatched} (+{$bonus})";
                        }
                    }
                }

                if ($currentRootId && (int) $leaf->parent_id === (int) $currentRootId) {
                    $score += $currentParentBonus;
                    $reasons[] = "parent actuel cohérent (+{$currentParentBonus})";
                }

                return [
                    'category' => $leaf,
                    'parent' => $leaf->parent,
                    'score' => $score,
                    'reasons' => array_values(array_unique($reasons)),
                ];
            })
            ->sortByDesc('score')
            ->values();

        $best = $candidates->get(0);
        $second = $candidates->get(1);
        $bestScore = (int) data_get($best, 'score', 0);
        $secondScore = (int) data_get($second, 'score', 0);
        $margin = $bestScore - $secondScore;

        $confident = $best
            && $bestScore >= $minimumScore
            && $margin >= $minimumMargin;

        return [
            'category' => $confident ? data_get($best, 'category') : null,
            'parent' => $confident ? data_get($best, 'parent') : null,
            'best_category' => data_get($best, 'category'),
            'best_parent' => data_get($best, 'parent'),
            'score' => $bestScore,
            'second_score' => $secondScore,
            'margin' => $margin,
            'confident' => (bool) $confident,
            'reasons' => data_get($best, 'reasons', []),
        ];
    }

    private function productFields(Product $product): array
    {
        return [
            'name' => $this->normalize((string) ($product->name ?? '')),
            'type' => $this->normalize((string) ($product->type ?? '')),
            'short_description' => $this->normalize((string) ($product->short_description ?? '')),
            'usage_area' => $this->normalize((string) ($product->usage_area ?? '')),
            'description' => $this->normalize((string) ($product->description ?? '')),
            'technical_details' => $this->normalize((string) ($product->technical_details ?? '')),
            'attributes' => $this->normalize($this->flattenAttributes($product->product_attributes ?? [])),
            'brand' => $this->normalize((string) ($product->brand ?? '')),
            'packaging' => $this->normalize((string) ($product->packaging ?? '')),
        ];
    }

    private function candidateAllowed(Product $product, Category $leaf): bool
    {
        $parentSlug = Str::slug((string) ($leaf->parent?->slug ?: $leaf->parent?->name));
        $reconditionedParents = (array) config('catalog_global_classifier.reconditioned_parent_slugs', []);

        if (! in_array($parentSlug, $reconditionedParents, true)) {
            return true;
        }

        $currentRootSlug = Str::slug((string) ($product->category?->slug ?: $product->category?->name));
        if (in_array($currentRootSlug, $reconditionedParents, true)) {
            return true;
        }

        $state = $this->normalize((string) ($product->product_state ?? ''));
        $allowedStates = collect((array) config('catalog_global_classifier.reconditioned_states', []))
            ->map(fn ($value) => $this->normalize((string) $value))
            ->all();

        return in_array($state, $allowedStates, true);
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
