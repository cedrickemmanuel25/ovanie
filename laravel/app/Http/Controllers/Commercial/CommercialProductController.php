<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Services\ProductImageNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommercialProductController extends Controller
{
    public function create(Request $request)
    {
        return view('commercial.products.create', $this->formData($request));
    }

    public function store(Request $request, ProductImageNormalizer $normalizer)
    {
        return $this->persist($request, $normalizer);
    }

    public function edit(Request $request, Product $product)
    {
        $this->assertManaged($request, $product);
        $product->load('images');

        return view('commercial.products.edit', [
            ...$this->formData($request),
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product, ProductImageNormalizer $normalizer)
    {
        $this->assertManaged($request, $product);

        return $this->persist($request, $normalizer, $product);
    }

    private function persist(Request $request, ProductImageNormalizer $normalizer, ?Product $product = null)
    {
        $publishing = $request->input('intent') === 'publish';
        $shopIds = $this->managedShops($request)->pluck('id');
        $rules = $this->rules($shopIds, $publishing);
        $data = $request->validate($rules, $this->messages());
        $shop = Shop::findOrFail($data['shop_id']);
        $removeIds = collect($request->input('remove_images', []))->map(fn ($id) => (int) $id)->filter()->all();
        $existingCount = $product ? $product->images()->whereNotIn('id', $removeIds)->count() : 0;
        $newFiles = array_values(array_filter((array) $request->file('images', [])));

        if (($existingCount + count($newFiles)) > 8) {
            throw ValidationException::withMessages(['images' => 'Un produit ne peut pas contenir plus de 8 images.']);
        }
        if ($publishing && ($existingCount + count($newFiles)) < 1) {
            throw ValidationException::withMessages(['images' => 'Ajoutez au moins une photo avant de publier le produit.']);
        }

        $payloads = $this->normalizeUploads($newFiles, $normalizer);
        $removedImages = collect();

        try {
            DB::transaction(function () use ($request, $product, $data, $shop, $publishing, $payloads, $removeIds, &$removedImages) {
                $attributes = $this->productAttributes($data, $publishing, $shop);
                if ($product) {
                    $product->fill($attributes)->save();
                    $removedImages = $product->images()->whereIn('id', $removeIds)->get();
                    $product->images()->whereIn('id', $removeIds)->delete();
                } else {
                    $product = Product::create([
                        ...$attributes,
                        'created_by_commercial_id' => $request->user()->id,
                        'slug' => filled($data['name'] ?? null)
                            ? Str::slug($data['name']) . '-' . Str::lower(Str::random(8))
                            : 'draft-' . Str::lower((string) Str::uuid()),
                    ]);
                }

                $hasSortOrder = Schema::hasColumn('product_images', 'sort_order');
                $position = $hasSortOrder ? (int) $product->images()->max('sort_order') + 1 : 0;
                foreach ($payloads as $index => $payload) {
                    $imageData = [...$payload, 'product_id' => $product->id];
                    if ($hasSortOrder) $imageData['sort_order'] = $position + $index;
                    ProductImage::create($imageData);
                }

                $mainId = (int) $request->input('main_existing_image_id');
                $mainNew = $request->filled('main_new_image_index') ? (int) $request->input('main_new_image_index') : null;
                $imagesQuery = $product->images();
                if ($hasSortOrder) $imagesQuery->orderBy('sort_order');
                $images = $imagesQuery->orderBy('id')->get();
                if ($mainNew !== null && isset($payloads[$mainNew])) {
                    $mainId = (int) $images->where('path', $payloads[$mainNew]['path'])->first()?->id;
                }
                if (! $images->contains('id', $mainId)) {
                    $mainId = (int) $images->first()?->id;
                }
                foreach ($images as $image) {
                    $values = ['is_main' => $image->id === $mainId];
                    if (Schema::hasColumn('product_images', 'is_primary')) $values['is_primary'] = $image->id === $mainId;
                    $image->update($values);
                }
            });
        } catch (Throwable $exception) {
            $this->deletePayloads($payloads, $normalizer);
            throw $exception;
        }

        foreach ($removedImages as $image) {
            $this->deleteImageFiles($image, $normalizer);
        }

        $message = ! $publishing
            ? 'Le produit a été enregistré comme brouillon.'
            : ($shop->canPublishProducts()
                ? 'Le produit a été publié.'
                : 'Le produit est complet, mais reste non publié tant que la logistique de la boutique n’est pas configurée.');

        return redirect()->route('commercial.products.index', ['shop_id' => $shop->id])->with('success', $message);
    }

    private function rules($shopIds, bool $publishing): array
    {
        $required = fn () => $publishing ? 'required' : 'nullable';
        return [
            'intent' => ['required', Rule::in(['draft', 'publish'])],
            'shop_id' => ['required', 'integer', Rule::in($shopIds->all())],
            'category_id' => [$required(), 'nullable', 'exists:categories,id'],
            'name' => [$required(), 'nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:150'], 'type' => ['nullable', 'string', 'max:150'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => [$required(), 'nullable', 'string', 'max:10000'],
            'price' => [$required(), 'nullable', 'integer', $publishing ? 'gt:0' : 'min:0'],
            'promo_price' => ['nullable', 'integer', 'gt:0', 'lt:price'],
            'stock' => ['nullable', 'integer', 'min:0'], 'unit' => [$required(), 'nullable', 'string', 'max:60'],
            'packaging' => ['nullable', 'string', 'max:255'],
            'min_order_quantity' => [$required(), 'nullable', 'numeric', 'gt:0'],
            'units_per_package' => ['nullable', 'numeric', 'gt:0'], 'supply_delay' => ['nullable', 'string', 'max:255'],
            'usage_area' => ['nullable', 'string', 'max:500'], 'technical_details' => ['nullable', 'string', 'max:10000'],
            'attributes' => ['nullable', 'array', 'max:30'], 'attributes.*.label' => ['nullable', 'string', 'max:100'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'], 'attributes.*.unit' => ['nullable', 'string', 'max:50'],
            'weight_kg' => [$required(), 'nullable', 'numeric', $publishing ? 'gt:0' : 'min:0'],
            'length_cm' => [$required(), 'nullable', 'numeric', $publishing ? 'gt:0' : 'min:0'],
            'width_cm' => [$required(), 'nullable', 'numeric', $publishing ? 'gt:0' : 'min:0'],
            'height_cm' => [$required(), 'nullable', 'numeric', $publishing ? 'gt:0' : 'min:0'],
            'fragile' => [$required(), 'nullable', Rule::in(['0', '1', 0, 1])],
            'is_negotiable' => ['nullable', 'boolean'],
            'price_p1' => ['nullable', 'required_if:is_negotiable,1', 'integer', 'min:1', 'lt:price'],
            'price_p2' => ['nullable', 'required_if:is_negotiable,1', 'integer', 'min:1', 'lt:price_p1'],
            'price_p3' => ['nullable', 'required_if:is_negotiable,1', 'integer', 'min:1', 'lt:price_p2'],
            'requires_unloading' => [$required(), 'nullable', Rule::in(['0', '1', 0, 1])],
            'unloading_instructions' => ['nullable', 'required_if:requires_unloading,1', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_images' => ['nullable', 'array'], 'remove_images.*' => ['integer'],
            'main_existing_image_id' => ['nullable', 'integer'], 'main_new_image_index' => ['nullable', 'integer', 'min:0', 'max:7'],
        ];
    }

    private function productAttributes(array $data, bool $publishing, Shop $shop): array
    {
        $attributes = collect($data['attributes'] ?? [])->map(fn ($item) => [
            'label' => trim((string) ($item['label'] ?? '')), 'value' => trim((string) ($item['value'] ?? '')), 'unit' => trim((string) ($item['unit'] ?? '')),
        ])->filter(fn ($item) => $item['label'] !== '' && $item['value'] !== '')->values()->all();
        $volume = null;
        if (! empty($data['length_cm']) && ! empty($data['width_cm']) && ! empty($data['height_cm'])) {
            $volume = round(((float) $data['length_cm'] * (float) $data['width_cm'] * (float) $data['height_cm']) / 1000000, 6);
        }
        return [
            ...collect($data)->only(['shop_id','category_id','name','brand','type','short_description','description','price','promo_price','stock','unit','packaging','min_order_quantity','units_per_package','supply_delay','usage_area','technical_details','weight_kg','length_cm','width_cm','height_cm','unloading_instructions','price_p1','price_p2','price_p3'])->all(),
            'stock' => (int) ($data['stock'] ?? 0), 'sale_type' => 'normal', 'product_attributes' => $attributes,
            'volume_m3' => $volume, 'fragile' => array_key_exists('fragile', $data) ? (bool) $data['fragile'] : false,
            'is_negotiable' => ! empty($data['is_negotiable'])
                && ! empty($data['price_p1']) && ! empty($data['price_p2']) && ! empty($data['price_p3']),
            'requires_unloading' => array_key_exists('requires_unloading', $data) ? (bool) $data['requires_unloading'] : false,
            'status' => $publishing ? ($shop->canPublishProducts() ? 'actif' : 'pending_logistics') : 'draft',
            'is_active' => $publishing && $shop->canPublishProducts(),
        ];
    }

    private function normalizeUploads(array $files, ProductImageNormalizer $normalizer): array
    {
        $payloads = [];
        foreach ($files as $file) {
            try { $result = $normalizer->normalize($file, 'products'); }
            catch (Throwable $e) {
                $this->deletePayloads($payloads, $normalizer);
                throw ValidationException::withMessages(['images' => 'La photo « '.$file->getClientOriginalName().' » est refusée : '.$e->getMessage().' Les formats HEIC/HEIF ne sont pas pris en charge.']);
            }
            $payload = ['path'=>$result['master'], 'original_path'=>$result['original'], 'card_path'=>$result['card'], 'thumb_path'=>$result['thumb'], 'original_width'=>$result['original_width'], 'original_height'=>$result['original_height'], 'normalized_at'=>now(), 'is_main'=>false];
            if (Schema::hasColumn('product_images', 'is_primary')) $payload['is_primary'] = false;
            $payloads[] = $payload;
        }
        return $payloads;
    }

    private function deletePayloads(array $payloads, ProductImageNormalizer $normalizer): void
    {
        foreach ($payloads as $payload) { $normalizer->deleteNormalizedSet(['master'=>$payload['path'],'card'=>$payload['card_path'],'thumb'=>$payload['thumb_path']]); Storage::disk(config('product-images.disk','public'))->delete($payload['original_path']); }
    }

    private function deleteImageFiles(ProductImage $image, ProductImageNormalizer $normalizer): void
    {
        $normalizer->deleteNormalizedSet(['master'=>$image->path,'card'=>$image->card_path,'thumb'=>$image->thumb_path]);
        Storage::disk(config('product-images.disk','public'))->delete(array_filter([$image->original_path]));
    }

    private function assertManaged(Request $request, Product $product): void
    {
        abort_unless($this->managedShops($request)->whereKey($product->shop_id)->exists(), 403);
    }

    private function formData(Request $request): array
    {
        return ['shops'=>$this->managedShops($request)->with('user:id,name,email')->orderBy('name')->get(), 'categories'=>Category::query()->orderBy('name')->get()];
    }

    private function managedShops(Request $request)
    {
        return Shop::query()->where(fn ($q) => $q->where('created_by_commercial_id',$request->user()->id)->orWhere('managed_by_commercial_id',$request->user()->id));
    }

    private function messages(): array
    {
        return ['images.*.mimes'=>'Format non pris en charge. Utilisez JPG, JPEG, PNG ou WEBP (pas HEIC/HEIF).','images.*.max'=>'Chaque photo doit peser au maximum 5 Mo.','weight_kg.required'=>'Le poids est obligatoire pour publier.','length_cm.required'=>'La longueur est obligatoire pour publier.','width_cm.required'=>'La largeur est obligatoire pour publier.','height_cm.required'=>'La hauteur est obligatoire pour publier.'];
    }
}
