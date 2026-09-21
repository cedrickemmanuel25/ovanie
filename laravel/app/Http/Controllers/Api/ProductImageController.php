<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductImageController extends Controller
{
    public function index()
    {
        $images = ProductImage::query()
            ->with('product:id,slug')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(
            PublicProductImageResource::collection($images)->resolve(request())
        );
    }

    public function show($id)
    {
        $image = ProductImage::query()->with('product:id,slug')->find($id);

        return $image
            ? response()->json((new PublicProductImageResource($image))->resolve(request()))
            : response()->json(['message' => 'Image produit non trouvée'], 404);
    }

    /**
     * Ajoute une image en appliquant le standard OVANIE : carré 1:1,
     * variantes master/card/thumb et conservation de l'original.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'path' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_main' => ['sometimes', 'boolean'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $this->authorize('create', [ProductImage::class, $product]);

        if ($product->images()->count() >= 10) {
            throw ValidationException::withMessages([
                'path' => 'Un produit peut contenir au maximum 10 images.',
            ]);
        }

        $normalized = $this->normalizeUpload($request, 'path');
        $isMain = (bool) ($validated['is_main'] ?? false)
            || ! $product->images()->where('is_main', true)->exists();

        try {
            if ($isMain) {
                $product->images()->update(['is_main' => false]);
                if (Schema::hasColumn('product_images', 'is_primary')) {
                    $product->images()->update(['is_primary' => false]);
                }
            }

            $attributes = $this->normalizedAttributes($product, $normalized, $isMain);
            $image = ProductImage::create($attributes);

            if ($isMain) {
                $this->syncProductMainImage($product, $normalized['master']);
            }
        } catch (Throwable $exception) {
            $this->deleteNormalizedUpload($normalized);
            throw $exception;
        }

        return response()->json($image->fresh(), 201);
    }

    /**
     * Remplace une image ou change l'image principale sans perdre l'ordre.
     */
    public function update(Request $request, $id)
    {
        $image = ProductImage::find($id);
        if (! $image) {
            return response()->json(['message' => 'Image produit non trouvée'], 404);
        }
        $this->authorize('update', $image);

        $validated = $request->validate([
            'product_id' => ['prohibited'],
            'path' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_main' => ['sometimes', 'boolean'],
        ]);

        $product = $image->product;
        $oldPaths = $this->storedPaths($image);
        $normalized = null;

        if ($request->hasFile('path')) {
            $normalized = $this->normalizeUpload($request, 'path');
        }

        try {
            if ($normalized !== null) {
                $image->path = $normalized['master'];
                $image->original_path = $normalized['original'];
                $image->card_path = $normalized['card'];
                $image->thumb_path = $normalized['thumb'];
                $image->original_width = $normalized['original_width'];
                $image->original_height = $normalized['original_height'];
                $image->normalized_at = now();
            }

            if (array_key_exists('is_main', $validated)) {
                if ($validated['is_main']) {
                    $product->images()->whereKeyNot($image->id)->update(['is_main' => false]);
                    if (Schema::hasColumn('product_images', 'is_primary')) {
                        $product->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
                        $image->is_primary = true;
                    }
                    $image->is_main = true;
                } elseif ($image->is_main) {
                    // Une galerie doit toujours garder une image principale.
                    $replacement = $product->images()->whereKeyNot($image->id)->orderBy('sort_order')->orderBy('id')->first();
                    if ($replacement) {
                        $replacement->forceFill(['is_main' => true])->save();
                        if (Schema::hasColumn('product_images', 'is_primary')) {
                            $replacement->forceFill(['is_primary' => true])->save();
                        }
                        $image->is_main = false;
                        if (Schema::hasColumn('product_images', 'is_primary')) {
                            $image->is_primary = false;
                        }
                    }
                }
            }

            $image->save();

            if ($image->is_main) {
                $this->syncProductMainImage($product, $image->path);
            } else {
                $main = $product->images()->where('is_main', true)->first();
                $this->syncProductMainImage($product, $main?->path);
            }
        } catch (Throwable $exception) {
            if ($normalized !== null) {
                $this->deleteNormalizedUpload($normalized);
            }
            throw $exception;
        }

        if ($normalized !== null) {
            $this->deleteStoredPaths($oldPaths);
        }

        return response()->json($image->fresh());
    }

    public function destroy($id)
    {
        $image = ProductImage::find($id);
        if (! $image) {
            return response()->json(['message' => 'Image produit non trouvée'], 404);
        }
        $this->authorize('delete', $image);

        $product = $image->product;
        $oldPaths = $this->storedPaths($image);
        $wasMain = (bool) $image->is_main;

        $replacement = null;
        if ($wasMain) {
            $replacement = $product->images()
                ->whereKeyNot($image->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
            if ($replacement) {
                $replacement->forceFill(['is_main' => true])->save();
                if (Schema::hasColumn('product_images', 'is_primary')) {
                    $replacement->forceFill(['is_primary' => true])->save();
                }
            }
        }

        $image->delete();
        $this->deleteStoredPaths($oldPaths);

        if ($wasMain) {
            $this->syncProductMainImage($product, $replacement?->path);
        }

        return response()->json(['message' => 'Image produit supprimée avec succès']);
    }

    /** @return array<string,mixed> */
    private function normalizeUpload(Request $request, string $field): array
    {
        try {
            return app(ProductImageNormalizer::class)->normalize(
                $request->file($field),
                'products'
            );
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                $field => 'Cette image ne respecte pas le standard OVANIE : ' . $exception->getMessage(),
            ]);
        }
    }

    /** @param array<string,mixed> $normalized */
    private function normalizedAttributes(Product $product, array $normalized, bool $isMain): array
    {
        $attributes = [
            'product_id' => $product->id,
            'path' => $normalized['master'],
            'original_path' => $normalized['original'],
            'card_path' => $normalized['card'],
            'thumb_path' => $normalized['thumb'],
            'original_width' => $normalized['original_width'],
            'original_height' => $normalized['original_height'],
            'normalized_at' => now(),
            'is_main' => $isMain,
        ];

        if (Schema::hasColumn('product_images', 'is_primary')) {
            $attributes['is_primary'] = $isMain;
        }
        if (Schema::hasColumn('product_images', 'sort_order')) {
            $attributes['sort_order'] = ((int) $product->images()->max('sort_order')) + 1;
        }

        return $attributes;
    }

    /** @return array<int,string> */
    private function storedPaths(ProductImage $image): array
    {
        return collect([
            $image->original_path,
            $image->path,
            $image->card_path,
            $image->thumb_path,
        ])->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<int,string> $paths */
    private function deleteStoredPaths(array $paths): void
    {
        $disk = Storage::disk((string) config('product-images.disk', 'public'));
        foreach ($paths as $path) {
            $clean = ltrim(str_replace('\\', '/', $path), '/');
            if ($clean !== '' && $disk->exists($clean)) {
                $disk->delete($clean);
            }
        }
    }

    /** @param array<string,mixed> $normalized */
    private function deleteNormalizedUpload(array $normalized): void
    {
        $this->deleteStoredPaths(array_values(array_filter([
            $normalized['original'] ?? null,
            $normalized['master'] ?? null,
            $normalized['card'] ?? null,
            $normalized['thumb'] ?? null,
        ], fn ($path) => is_string($path) && trim($path) !== '')));
    }

    private function syncProductMainImage(Product $product, ?string $path): void
    {
        if (Schema::hasColumn($product->getTable(), 'main_image')) {
            $product->forceFill(['main_image' => $path])->save();
        }
    }
}
