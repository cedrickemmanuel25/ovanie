<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductVariantService
{
    public function sync(Product $product, Request $request): void
    {
        $enabled = $request->boolean('has_variants') || is_array($request->input('variants'));
        if (! $enabled) {
            if ($request->has('has_variants')) $this->deleteAll($product);
            return;
        }

        $rows = collect((array) $request->input('variants', []))
            ->map(fn ($row, $index) => is_array($row) ? [...$row, '_index' => $index] : null)
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['value'] ?? '')) !== '')
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['variants' => 'Ajoutez au moins un modèle/couleur ou désactivez les variantes.']);
        }

        $normalizer = app(ProductImageNormalizer::class);
        $keepIds = [];

        DB::transaction(function () use ($product, $request, $rows, $normalizer, &$keepIds) {
            foreach ($rows as $position => $row) {
                $id = isset($row['id']) ? (int) $row['id'] : null;
                $variant = $id
                    ? $product->variants()->whereKey($id)->first()
                    : new ProductVariant(['product_id' => $product->id]);

                if (! $variant) continue;

                $variant->fill([
                    'type' => trim((string) ($row['type'] ?? 'Couleur')) ?: 'Couleur',
                    'value' => trim((string) $row['value']),
                    'color_hex' => $this->normalizeHex($row['color_hex'] ?? null),
                    'stock' => max(0, (int) ($row['stock'] ?? 0)),
                    'price_override' => filled($row['price_override'] ?? null) ? max(0, (float) $row['price_override']) : null,
                    'is_active' => ! array_key_exists('is_active', $row) || filter_var($row['is_active'], FILTER_VALIDATE_BOOL),
                    'sort_order' => $position,
                ]);

                $file = $request->file('variant_images.' . ($row['_index'] ?? $position));
                if ($file && $file->isValid()) {
                    try {
                        $result = $normalizer->normalize($file, 'products/variants');
                    } catch (Throwable $e) {
                        throw ValidationException::withMessages(['variant_images' => 'Image variante invalide : ' . $e->getMessage()]);
                    }
                    $this->deleteVariantFiles($variant);
                    $variant->image_path = $result['master'] ?? null;
                    $variant->card_path = $result['card'] ?? null;
                    $variant->thumb_path = $result['thumb'] ?? null;
                }

                if ($variant->is_active && ! $variant->image_path && ! $variant->card_path && ! $variant->thumb_path) {
                    throw ValidationException::withMessages([
                        'variant_images' => 'Ajoutez une image correspondant exactement à la variante « '.$variant->value.' ».',
                    ]);
                }

                $variant->save();
                $keepIds[] = $variant->id;
            }

            $obsolete = $product->variants()->when($keepIds, fn ($q) => $q->whereNotIn('id', $keepIds))->get();
            foreach ($obsolete as $variant) {
                $this->deleteVariantFiles($variant);
                $variant->delete();
            }

            $product->forceFill([
                'stock' => (int) $product->variants()->where('is_active', true)->sum('stock'),
            ])->save();
        });
    }

    public function deleteAll(Product $product): void
    {
        foreach ($product->variants()->get() as $variant) {
            $this->deleteVariantFiles($variant);
            $variant->delete();
        }
    }

    private function normalizeHex(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') return null;
        if ($value[0] !== '#') $value = '#' . $value;
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : null;
    }

    private function deleteVariantFiles(ProductVariant $variant): void
    {
        foreach (array_unique(array_filter([$variant->image_path, $variant->card_path, $variant->thumb_path])) as $path) {
            $path = preg_replace('#^/?storage/#', '', (string) $path);
            if ($path && Storage::disk('public')->exists($path)) Storage::disk('public')->delete($path);
        }
    }
}
