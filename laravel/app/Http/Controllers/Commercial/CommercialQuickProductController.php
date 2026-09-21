<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CommercialQuickProductService;
use App\Services\ProductImageNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommercialQuickProductController extends Controller
{
    public function create(Request $request)
    {
        $shops = $this->managedShops($request)
            ->with('user:id,name,email')
            ->orderBy('name')
            ->get();

        $selectedShop = $shops->firstWhere('id', (int) $request->query('shop_id'))
            ?: $shops->first();

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $recentProducts = collect();
        $addedToday = 0;

        if ($selectedShop) {
            $recentProducts = Product::query()
                ->with(['images', 'masterProduct'])
                ->where('shop_id', $selectedShop->id)
                ->where('created_by_commercial_id', $request->user()->id)
                ->latest()
                ->limit(8)
                ->get();

            $addedToday = Product::query()
                ->where('shop_id', $selectedShop->id)
                ->where('created_by_commercial_id', $request->user()->id)
                ->whereDate('created_at', today())
                ->count();
        }

        return view('commercial.products.quick-create', compact(
            'shops',
            'selectedShop',
            'categories',
            'recentProducts',
            'addedToday'
        ));
    }

    public function search(
        Request $request,
        CommercialQuickProductService $quickService
    ) {
        $data = $request->validate([
            'shop_id' => ['required', 'integer'],
            'q' => ['bail', 'required', 'string', 'min:2', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ], [
            'q.required' => 'Saisissez le nom, la marque, le SKU ou le code-barres du produit.',
            'q.min' => 'Saisissez au moins 2 caractères pour lancer la recherche.',
        ]);

        $shop = $this->managedShops($request)->findOrFail($data['shop_id']);
        $term = trim($data['q']);
        $categoryId = filled($data['category_id'] ?? null)
            ? (int) $data['category_id']
            : null;

        return response()->json(
            $quickService->searchReferences($shop, $term, $categoryId, 20)
        );
    }

    public function store(
        Request $request,
        CommercialQuickProductService $quickService,
        ProductImageNormalizer $normalizer
    ) {
        $shopIds = $this->managedShops($request)->pluck('id')->all();

        $data = $request->validate([
            'shop_id' => ['required', 'integer', Rule::in($shopIds)],
            'catalog_source_type' => ['nullable', Rule::in(['master', 'marketplace'])],
            'catalog_source_id' => ['nullable', 'integer', 'min:1'],
            // Compatibilité avec l'ancienne page éventuellement encore en cache.
            'master_product_id' => ['nullable', 'integer', 'exists:master_products,id'],
            'price' => ['required', 'integer', 'gt:0'],
            'promo_price' => ['nullable', 'integer', 'gt:0', 'lt:price'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_order_quantity' => ['required', 'integer', 'min:1'],
            'availability_status' => [
                'required',
                Rule::in(['in_stock', 'on_order', 'preorder', 'out_of_stock']),
            ],
            'intent' => ['required', Rule::in(['publish', 'draft'])],
            'use_catalog_image' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'shop_id.required' => 'Sélectionnez une boutique.',
            'price.required' => 'Le prix est obligatoire.',
            'price.gt' => 'Le prix doit être supérieur à zéro.',
            'stock.required' => 'Le stock est obligatoire.',
            'photo.mimes' => 'La photo doit être au format JPG, PNG ou WEBP.',
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
        ]);

        $sourceType = (string) ($data['catalog_source_type'] ?? '');
        $sourceId = (int) ($data['catalog_source_id'] ?? 0);

        if ($sourceType === '' && ! empty($data['master_product_id'])) {
            $sourceType = 'master';
            $sourceId = (int) $data['master_product_id'];
        }

        if (! in_array($sourceType, ['master', 'marketplace'], true) || $sourceId < 1) {
            throw ValidationException::withMessages([
                'catalog_source_id' => 'Recherchez puis sélectionnez un produit avant de saisir le prix et le stock.',
            ]);
        }

        $shop = $this->managedShops($request)->findOrFail($data['shop_id']);

        try {
            $masterProduct = $quickService->resolveMasterReference($sourceType, $sourceId);

            $result = $quickService->upsert(
                (int) $request->user()->id,
                $shop,
                $masterProduct,
                $data,
                $data['intent'] === 'publish',
                $request->file('photo'),
                $request->boolean('use_catalog_image'),
                $normalizer
            );
        } catch (Throwable $exception) {
            Log::error('Échec ajout rapide produit commercial', [
                'commercial_id' => $request->user()->id,
                'shop_id' => $shop->id,
                'catalog_source_type' => $sourceType,
                'catalog_source_id' => $sourceId,
                'exception' => $exception,
            ]);

            throw ValidationException::withMessages([
                'catalog_source_id' => 'Le produit n’a pas pu être enregistré : ' . $exception->getMessage(),
            ]);
        }

        $verb = $result['created'] ? 'ajouté' : 'mis à jour';
        $message = match ($result['status']) {
            'actif' => "Produit {$verb} et publié.",
            'pending_logistics' => "Produit {$verb}, mais non publié : la logistique de la boutique est incomplète.",
            default => $result['missing'] !== []
                ? "Produit {$verb} en brouillon. À compléter : " . implode(', ', $result['missing']) . '.'
                : "Produit {$verb} en brouillon.",
        };

        if ($sourceType === 'marketplace') {
            $message = 'Référence technique créée automatiquement à partir du produit déjà en ligne. ' . $message;
        }

        return redirect()
            ->route('commercial.products.quick.create', ['shop_id' => $shop->id])
            ->with('success', $message)
            ->with('quick_product_id', $result['product']->id);
    }

    public function storeUnknown(
        Request $request,
        CommercialQuickProductService $quickService,
        ProductImageNormalizer $normalizer
    ) {
        $shopIds = $this->managedShops($request)->pluck('id')->all();

        $data = $request->validate([
            'shop_id' => ['required', 'integer', Rule::in($shopIds)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:150'],
            'price' => ['required', 'integer', 'gt:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'unit' => ['required', Rule::in([
                'piece', 'sac', 'kg', 'tonne', 'm2', 'm3', 'litre', 'carton',
                'palette', 'rouleau', 'seau', 'paquet', 'barre', 'bidon',
            ])],
            'min_order_quantity' => ['required', 'integer', 'min:1'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'name.required' => 'Indiquez le nom du produit.',
            'category_id.required' => 'Choisissez une catégorie.',
            'price.required' => 'Indiquez le prix de vente.',
            'stock.required' => 'Indiquez le stock disponible.',
            'unit.required' => 'Choisissez l’unité de vente.',
            'photo.required' => 'Prenez au moins une photo du produit.',
            'photo.mimes' => 'La photo doit être au format JPG, PNG ou WEBP.',
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
        ]);

        $shop = $this->managedShops($request)->findOrFail($data['shop_id']);

        try {
            $product = $quickService->createFieldDraft(
                (int) $request->user()->id,
                $shop,
                $data,
                $request->file('photo'),
                $normalizer
            );
        } catch (Throwable $exception) {
            Log::error('Échec capture terrain produit absent', [
                'commercial_id' => $request->user()->id,
                'shop_id' => $shop->id,
                'exception' => $exception,
            ]);

            throw ValidationException::withMessages([
                'photo' => 'Le brouillon n’a pas pu être enregistré : ' . $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('commercial.products.quick.create', ['shop_id' => $shop->id])
            ->with('success', 'Produit photographié et enregistré dans « À compléter ». Il n’est pas encore visible par les clients.')
            ->with('quick_product_id', $product->id);
    }

    /**
     * L'import CSV n'est pas présenté dans le parcours terrain.
     */
    public function importForm(Request $request)
    {
        return redirect()
            ->route('commercial.products.quick.create', ['shop_id' => $request->query('shop_id')])
            ->with('success', 'L’import CSV a été retiré du parcours terrain. Recherchez une référence existante ou photographiez le produit.');
    }

    public function downloadTemplate(Request $request)
    {
        return $this->importForm($request);
    }

    public function import(Request $request)
    {
        return $this->importForm($request);
    }

    private function managedShops(Request $request): Builder
    {
        return Shop::query()->where(function (Builder $query) use ($request) {
            $query->where('created_by_commercial_id', $request->user()->id)
                ->orWhere('managed_by_commercial_id', $request->user()->id);
        });
    }
}
