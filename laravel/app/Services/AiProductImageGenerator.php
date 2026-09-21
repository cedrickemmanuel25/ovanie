<?php

namespace App\Services;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Régénère une photo produit brute (téléphone, galerie) en une photo de
 * catalogue professionnelle via l'API OpenAI (images/edits), puis la fait
 * repasser par ProductImageNormalizer pour produire les mêmes variantes
 * carrées (master/card/thumb en WEBP) que le reste du catalogue.
 *
 * Ne modifie jamais original_path : la photo brute reste disponible en
 * interne pour audit, seule la version générée devient publique.
 */
class AiProductImageGenerator
{
    private const PROMPT = 'Professional e-commerce product photography. '
        . 'Keep the exact same product with its exact shape, colors, text, '
        . 'logos and proportions unchanged. Place it on a clean, evenly lit '
        . 'studio background suited for an online marketplace catalog. '
        . 'Do not add, remove, invent or alter any feature of the product.';

    public function __construct(
        private readonly ProductImageNormalizer $normalizer,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('product-images.ai_enhancement_enabled', false)
            && filled(config('services.openai.key'));
    }

    /**
     * @return bool true si l'image a été régénérée et remplacée avec succès.
     */
    public function generate(ProductImage $image): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $disk = Storage::disk((string) config('product-images.disk', 'public'));
        $sourcePath = $image->original_path ?: $image->path;

        if (! is_string($sourcePath) || $sourcePath === '' || ! $disk->exists($sourcePath)) {
            return false;
        }

        try {
            $binary = $disk->get($sourcePath);
            $generated = $this->requestGeneratedImage($binary, $sourcePath);

            if ($generated === null) {
                $image->forceFill(['ai_image_status' => 'failed'])->saveQuietly();

                return false;
            }

            $tempPath = 'products/ai-source/' . now()->format('Y/m') . '/' . Str::uuid() . '.png';
            $disk->put($tempPath, $generated);

            try {
                $result = $this->normalizer->normalizeStoredPath($tempPath, 'products');
            } finally {
                $disk->delete($tempPath);
            }

            $image->forceFill([
                'path' => $result['master'],
                'card_path' => $result['card'],
                'thumb_path' => $result['thumb'],
                'ai_image_status' => 'done',
                'ai_image_generated_at' => now(),
            ])->save();

            return true;
        } catch (Throwable $exception) {
            Log::error('AI product image generation failed', [
                'product_image_id' => $image->id,
                'message' => $exception->getMessage(),
            ]);

            $image->forceFill(['ai_image_status' => 'failed'])->saveQuietly();

            return false;
        }
    }

    private function requestGeneratedImage(string $binary, string $sourcePath): ?string
    {
        $response = Http::withToken((string) config('services.openai.key'))
            ->timeout(60)
            ->attach('image', $binary, basename($sourcePath))
            ->post('https://api.openai.com/v1/images/edits', [
                'model' => (string) config('services.openai.image_model', 'gpt-image-1'),
                'prompt' => self::PROMPT,
                'size' => '1024x1024',
            ]);

        if (! $response->successful()) {
            Log::warning('OpenAI image edit request failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            return null;
        }

        $base64 = $response->json('data.0.b64_json');

        if (! is_string($base64) || $base64 === '') {
            return null;
        }

        $decoded = base64_decode($base64, true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }
}
