<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MasterProduct;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterProductController extends Controller
{
    public function index(Request $request)
    {
        $referenceSearch = trim((string) $request->input('reference_search'));
        $referenceState = (string) $request->input('reference_state', 'all');
        $referenceLogistics = (string) $request->input('reference_logistics', 'all');
        $referenceCategory = $request->integer('reference_category');

        $masterQuery = MasterProduct::query()
            ->with('category')
            ->withCount('products');

        $masterQuery
            ->when($referenceSearch !== '', function (Builder $query) use ($referenceSearch) {
                $query->where(function (Builder $searchQuery) use ($referenceSearch) {
                    $like = '%'.$referenceSearch.'%';
                    $searchQuery
                        ->where('name', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', $like));
                });
            })
            ->when($referenceCategory > 0, fn (Builder $query) => $query->where('category_id', $referenceCategory))
            ->when($referenceState === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($referenceState === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($referenceLogistics === 'complete', fn (Builder $query) => $this->applyCompleteLogistics($query))
            ->when($referenceLogistics === 'incomplete', function (Builder $query) {
                $query->where(function (Builder $incomplete) {
                    $incomplete
                        ->whereNull('weight_kg')->orWhere('weight_kg', '<=', 0)
                        ->orWhereNull('length_cm')->orWhere('length_cm', '<=', 0)
                        ->orWhereNull('width_cm')->orWhere('width_cm', '<=', 0)
                        ->orWhereNull('height_cm')->orWhere('height_cm', '<=', 0);
                });
            });

        $masterProducts = $masterQuery
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15, ['*'], 'references_page')
            ->withQueryString();

        $productSearch = trim((string) $request->input('product_search'));
        $productCategory = $request->integer('product_category');
        $productShop = $request->integer('product_shop');
        $productQuality = (string) $request->input('product_quality', 'all');

        $unlinkedQuery = Product::query()
            ->with([
                'shop:id,name',
                'category:id,name',
                'images',
            ])
            ->whereNull('master_product_id')
            ->notArchived();

        $unlinkedQuery
            ->when($productSearch !== '', function (Builder $query) use ($productSearch) {
                $query->where(function (Builder $searchQuery) use ($productSearch) {
                    $like = '%'.$productSearch.'%';
                    $searchQuery
                        ->where('name', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhereHas('shop', fn (Builder $shopQuery) => $shopQuery->where('name', 'like', $like))
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', $like));
                });
            })
            ->when($productCategory > 0, fn (Builder $query) => $query->where('category_id', $productCategory))
            ->when($productShop > 0, fn (Builder $query) => $query->where('shop_id', $productShop))
            ->when($productQuality === 'ready', fn (Builder $query) => $this->applyCompleteProductData($query))
            ->when($productQuality === 'incomplete', function (Builder $query) {
                $query->where(function (Builder $incomplete) {
                    $incomplete
                        ->whereNull('weight_kg')->orWhere('weight_kg', '<=', 0)
                        ->orWhereNull('length_cm')->orWhere('length_cm', '<=', 0)
                        ->orWhereNull('width_cm')->orWhere('width_cm', '<=', 0)
                        ->orWhereNull('height_cm')->orWhere('height_cm', '<=', 0)
                        ->orWhereNull('category_id')
                        ->orWhereNull('unit');
                });
            })
            ->when($productQuality === 'text_issue', function (Builder $query) {
                $query->where(function (Builder $textQuery) {
                    $textQuery
                        ->where('name', 'like', '%??%')
                        ->orWhere('name', 'like', '%�%')
                        ->orWhere('name', 'like', '%Ã%');
                });
            });

        $unlinkedProducts = $unlinkedQuery
            ->latest('id')
            ->paginate(12, ['*'], 'products_page')
            ->withQueryString();

        $stats = [
            'references_total' => MasterProduct::query()->count(),
            'references_active' => MasterProduct::query()->where('is_active', true)->count(),
            'references_incomplete' => MasterProduct::query()
                ->where(function (Builder $query) {
                    $query
                        ->whereNull('weight_kg')->orWhere('weight_kg', '<=', 0)
                        ->orWhereNull('length_cm')->orWhere('length_cm', '<=', 0)
                        ->orWhereNull('width_cm')->orWhere('width_cm', '<=', 0)
                        ->orWhereNull('height_cm')->orWhere('height_cm', '<=', 0);
                })
                ->count(),
            'linked_offers' => Product::query()->whereNotNull('master_product_id')->count(),
            'unlinked_products' => Product::query()->whereNull('master_product_id')->notArchived()->count(),
        ];

        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $shops = Shop::query()->orderBy('name')->get(['id', 'name']);

        $activeTab = (string) $request->input('tab');
        if (! in_array($activeTab, ['references', 'products'], true)) {
            $activeTab = $stats['references_total'] === 0 ? 'products' : 'references';
        }

        return view('admin.master-products.index', compact(
            'masterProducts',
            'unlinkedProducts',
            'stats',
            'categories',
            'shops',
            'activeTab',
        ));
    }

    public function create(Request $request)
    {
        $sourceProduct = null;
        $masterProduct = new MasterProduct();

        if ($request->filled('source_product_id')) {
            $sourceProduct = Product::query()
                ->with(['shop:id,name', 'category:id,name'])
                ->whereNull('master_product_id')
                ->findOrFail($request->integer('source_product_id'));

            $masterProduct->fill([
                'name' => $sourceProduct->name,
                'brand' => $sourceProduct->brand,
                'reference' => null,
                'sku' => $sourceProduct->sku,
                'category_id' => $sourceProduct->category_id,
                'unit' => $sourceProduct->unit,
                'packaging' => $sourceProduct->packaging,
                'weight_kg' => $sourceProduct->weight_kg ?: $sourceProduct->weight,
                'volume_m3' => $sourceProduct->volume_m3,
                'length_cm' => $sourceProduct->length_cm,
                'width_cm' => $sourceProduct->width_cm,
                'height_cm' => $sourceProduct->height_cm,
                'color' => $sourceProduct->color,
                'grade' => $sourceProduct->material_grade,
                'standard' => $sourceProduct->standard,
                'description' => $sourceProduct->description,
                'short_description' => $sourceProduct->short_description,
                'technical_details' => $sourceProduct->technical_details,
                'product_attributes' => $sourceProduct->product_attributes,
                'fragile' => $sourceProduct->fragile,
                'requires_unloading' => $sourceProduct->requires_unloading,
                'unloading_instructions' => $sourceProduct->unloading_instructions,
                'is_active' => true,
            ]);
        }

        return view('admin.master-products.create', [
            'masterProduct' => $masterProduct,
            'categories' => Category::orderBy('name')->get(),
            'sourceProduct' => $sourceProduct,
        ]);
    }

    public function store(Request $request)
    {
        $sourceProductId = $request->validate([
            'source_product_id' => ['nullable', 'integer', 'exists:products,id'],
        ])['source_product_id'] ?? null;

        $masterProduct = DB::transaction(function () use ($request, $sourceProductId) {
            $masterProduct = MasterProduct::create($this->validated($request));

            if ($sourceProductId) {
                Product::query()
                    ->whereKey($sourceProductId)
                    ->whereNull('master_product_id')
                    ->update([
                        'master_product_id' => $masterProduct->id,
                        'is_fulfillment_enabled' => true,
                    ]);
            }

            return $masterProduct;
        });

        return redirect()
            ->route('admin.master-products.edit', $masterProduct)
            ->with('success', $sourceProductId
                ? 'Référence créée et produit de la boutique rattaché.'
                : 'Référence créée. Vous pouvez maintenant associer les produits des boutiques.');
    }

    public function edit(MasterProduct $masterProduct)
    {
        $masterProduct->load('products.shop', 'products.category');

        $availableProducts = Product::query()
            ->with('shop', 'category')
            ->where(function ($query) use ($masterProduct) {
                $query->whereNull('master_product_id')
                    ->orWhere('master_product_id', $masterProduct->id);
            })
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('admin.master-products.edit', [
            'masterProduct' => $masterProduct,
            'categories' => Category::orderBy('name')->get(),
            'availableProducts' => $availableProducts,
        ]);
    }

    public function update(Request $request, MasterProduct $masterProduct)
    {
        $masterProduct->update($this->validated($request));

        return back()->with('success', 'Référence mise à jour.');
    }

    public function attachProducts(Request $request, MasterProduct $masterProduct)
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        Product::whereKey($data['product_ids'])->update([
            'master_product_id' => $masterProduct->id,
            'is_fulfillment_enabled' => true,
        ]);

        return back()->with('success', 'Produits associés à la référence technique.');
    }

    private function applyCompleteLogistics(Builder $query): Builder
    {
        return $query
            ->where('weight_kg', '>', 0)
            ->where('length_cm', '>', 0)
            ->where('width_cm', '>', 0)
            ->where('height_cm', '>', 0);
    }

    private function applyCompleteProductData(Builder $query): Builder
    {
        return $query
            ->whereNotNull('category_id')
            ->whereNotNull('unit')
            ->where('weight_kg', '>', 0)
            ->where('length_cm', '>', 0)
            ->where('width_cm', '>', 0)
            ->where('height_cm', '>', 0);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:80'],
            'packaging' => ['nullable', 'string', 'max:255'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'volume_m3' => ['nullable', 'numeric', 'min:0'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:120'],
            'standard' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:10000'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'technical_details' => ['nullable', 'string', 'max:10000'],
            'product_attributes' => ['nullable', 'array', 'max:30'],
            'product_attributes.*.label' => ['nullable', 'string', 'max:120'],
            'product_attributes.*.value' => ['nullable', 'string', 'max:255'],
            'product_attributes.*.unit' => ['nullable', 'string', 'max:50'],
            'fragile' => ['nullable', 'boolean'],
            'requires_unloading' => ['nullable', 'boolean'],
            'unloading_instructions' => [
                $request->input('requires_unloading') === '1' ? 'required' : 'nullable',
                'string',
                'max:2000',
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (
            (float) ($data['length_cm'] ?? 0) > 0
            && (float) ($data['width_cm'] ?? 0) > 0
            && (float) ($data['height_cm'] ?? 0) > 0
        ) {
            $data['volume_m3'] = round(
                ((float) $data['length_cm'] * (float) $data['width_cm'] * (float) $data['height_cm']) / 1_000_000,
                6
            );
        }

        $data['product_attributes'] = collect($data['product_attributes'] ?? [])
            ->map(fn ($attribute) => [
                'label' => trim((string) ($attribute['label'] ?? '')),
                'value' => trim((string) ($attribute['value'] ?? '')),
                'unit' => trim((string) ($attribute['unit'] ?? '')),
            ])
            ->filter(fn ($attribute) => $attribute['label'] !== '' && $attribute['value'] !== '')
            ->values()
            ->all();

        $data['fragile'] = $request->filled('fragile')
            ? $request->boolean('fragile')
            : null;
        $data['requires_unloading'] = $request->filled('requires_unloading')
            ? $request->boolean('requires_unloading')
            : null;
        $data['unloading_instructions'] = $data['requires_unloading']
            ? ($data['unloading_instructions'] ?? null)
            : null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
