<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Suggère une catégorie/sous-catégorie à partir du seul nom du produit, pour
 * que le vendeur ou le commercial n'ait plus besoin de la choisir à la main
 * (source d'erreurs de catégorisation). L'IA choisit obligatoirement parmi
 * les catégories qui existent réellement en base - jamais une catégorie
 * inventée - et la suggestion reste toujours modifiable côté formulaire.
 */
class AiProductCategorySuggester
{
    public function isEnabled(): bool
    {
        return (bool) config('product-categorization.ai_suggestion_enabled', false)
            && filled(config('services.openai.key'));
    }

    /**
     * @return array{category_id: int, subcategory_id: ?int}|null
     */
    public function suggest(string $productName): ?array
    {
        $productName = trim($productName);

        if (! $this->isEnabled() || $productName === '') {
            return null;
        }

        $categories = Category::query()
            ->active()
            ->ordered()
            ->get(['id', 'parent_id', 'name']);

        if ($categories->isEmpty()) {
            return null;
        }

        $catalog = $this->catalogForPrompt($categories);

        try {
            $chosenId = $this->requestCategoryId($productName, $catalog);
        } catch (Throwable $exception) {
            Log::warning('AI product category suggestion failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($chosenId === null) {
            return null;
        }

        $chosen = $categories->firstWhere('id', $chosenId);

        if (! $chosen) {
            // L'IA a répondu un identifiant hors de la liste fournie : on
            // ignore la suggestion plutôt que de risquer une fausse catégorie.
            Log::info('AI product category suggestion: returned id not found in the real catalog', [
                'product_name' => $productName,
                'returned_category_id' => $chosenId,
            ]);

            return null;
        }

        return $chosen->parent_id
            ? ['category_id' => (int) $chosen->parent_id, 'subcategory_id' => (int) $chosen->id]
            : ['category_id' => (int) $chosen->id, 'subcategory_id' => null];
    }

    private function catalogForPrompt(\Illuminate\Support\Collection $categories): string
    {
        $roots = $categories->whereNull('parent_id');
        $lines = [];

        foreach ($roots as $root) {
            $lines[] = "{$root->id}: {$root->name}";
            foreach ($categories->where('parent_id', $root->id) as $child) {
                $lines[] = "  {$child->id}: {$child->name} (sous-catégorie de {$root->name})";
            }
        }

        return implode("\n", $lines);
    }

    private function requestCategoryId(string $productName, string $catalog): ?int
    {
        $response = Http::withToken((string) config('services.openai.key'))
            ->timeout(15)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('services.openai.text_model', 'gpt-4o-mini'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu classes des produits de quincaillerie/BTP dans un catalogue OVANIE. '
                            . "Voici la liste des catégories et sous-catégories existantes, une par ligne, au format \"identifiant: nom\" :\n"
                            . $catalog
                            . "\n\nChoisis l'identifiant le plus précis possible (une sous-catégorie si le produit y correspond clairement, "
                            . 'sinon la catégorie principale). Réponds uniquement en JSON strict au format '
                            . '{"category_id": <identifiant entier tiré EXACTEMENT de la liste ci-dessus>}. '
                            . 'N\'invente jamais un identifiant absent de la liste. Si aucune catégorie ne correspond raisonnablement, '
                            . 'réponds {"category_id": null}.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Nom du produit : \"{$productName}\"",
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('OpenAI category suggestion request failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            return null;
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || $content === '') {
            Log::info('AI product category suggestion: empty content from OpenAI', [
                'product_name' => $productName,
                'response_body' => Str::limit($response->body(), 800),
            ]);

            return null;
        }

        $decoded = json_decode($content, true);
        $categoryId = is_array($decoded) ? ($decoded['category_id'] ?? null) : null;

        if (! is_numeric($categoryId)) {
            Log::info('AI product category suggestion: no usable category_id in the AI response', [
                'product_name' => $productName,
                'content' => Str::limit($content, 500),
            ]);

            return null;
        }

        return (int) $categoryId;
    }
}
