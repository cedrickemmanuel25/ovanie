<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Rules\MarketplaceProductImage;
use App\Services\PayDunyaService;
use App\Services\ProductImageNormalizer;
use App\Services\SellerLogisticsValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class VendorProductController extends Controller
{
    private const DEFAULT_BOOST_PRICE = 1400;
    private const DEFAULT_BOOST_DAYS = 7;

    private const BOOST_PACKAGES = [
        'semaine_classic' => [
            'label' => 'SEMAINE - Classic',
            'price' => 1400,
            'days' => 7,
            'type' => 'classic',
            'priority' => 1,
        ],
        'semaine_classic_email' => [
            'label' => 'SEMAINE - Classic + email',
            'price' => 2450,
            'days' => 7,
            'type' => 'classic_email',
            'priority' => 2,
        ],
        'semaine_classic_sms_email' => [
            'label' => 'SEMAINE - Classic + SMS + email',
            'price' => 5600,
            'days' => 7,
            'type' => 'classic_sms_email',
            'priority' => 3,
        ],
        'mois_classic' => [
            'label' => 'MOIS - Classic',
            'price' => 5000,
            'days' => 30,
            'type' => 'classic',
            'priority' => 4,
        ],
        'mois_classic_email' => [
            'label' => 'MOIS - Classic + email',
            'price' => 8750,
            'days' => 30,
            'type' => 'classic_email',
            'priority' => 5,
        ],
        'mois_classic_sms_email' => [
            'label' => 'MOIS - Classic + SMS + email',
            'price' => 20000,
            'days' => 30,
            'type' => 'classic_sms_email',
            'priority' => 6,
        ],
        'premium_classic' => [
            'label' => 'PREMIUM - Classic',
            'price' => 15000,
            'days' => 90,
            'type' => 'premium_classic',
            'priority' => 7,
        ],
        'premium_classic_email' => [
            'label' => 'PREMIUM - Classic + email',
            'price' => 26250,
            'days' => 90,
            'type' => 'premium_classic_email',
            'priority' => 8,
        ],
        'premium_classic_sms_email' => [
            'label' => 'PREMIUM - Classic + SMS + email',
            'price' => 60000,
            'days' => 90,
            'type' => 'premium_classic_sms_email',
            'priority' => 9,
        ],
    ];

    private const BTP_UNITS = [
        'sac',
        'tonne',
        'm3',
        'm2',
        'ml',
        'piece',
        'palette',
        'rouleau',
        'seau',
        'carton',
        'paquet',
        'barre',
        'bidon',
        'kg',
        'litre',
    ];

    public function __construct()
    {
        $this->middleware('auth')->except(['paydunyaWebhook']);
    }

    private function getBoostPackage(string $key): array
    {
        return self::BOOST_PACKAGES[$key] ?? self::BOOST_PACKAGES['semaine_classic'];
    }

    private function ensureOwnsProduct(Product $product): void
    {
        $shop = Auth::user()?->shop;

        if (! $shop || (int) $product->shop_id !== (int) $shop->id) {
            abort(403, 'Accès non autorisé.');
        }
    }

    public function index()
    {
        $user = Auth::user();
        $shop = $user->shop;

        if (! $shop) {
            return redirect()->route('open-shop')
                ->with('error', 'Vous devez créer une boutique.');
        }

        $statusFilter = request('status', 'catalogue');

        $productsQuery = Product::with('images', 'category')
            ->where('shop_id', $shop->id);

        if ($statusFilter === 'archived') {
            $productsQuery->archived();
        } else {
            $productsQuery->notArchived();
        }

        $products = $productsQuery
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $totalProducts = Product::where('shop_id', $shop->id)
            ->notArchived()
            ->count();

        $archivedProducts = Product::where('shop_id', $shop->id)
            ->archived()
            ->count();

        $activeProducts = Product::where('shop_id', $shop->id)
            ->notArchived()
            ->where('is_active', true)
            ->count();

        $lowStockProducts = Product::where('shop_id', $shop->id)
            ->notArchived()
            ->where('stock', '<=', 5)
            ->count();

        $boostedProducts = Product::where('shop_id', $shop->id)
            ->notArchived()
            ->activeBoosted()
            ->count();

        $boostPrice = self::DEFAULT_BOOST_PRICE;
        $boostPackages = self::BOOST_PACKAGES;
        $categories = Category::orderBy('name')->get();

        return view('vendor.products.index', compact(
            'products',
            'totalProducts',
            'activeProducts',
            'lowStockProducts',
            'boostedProducts',
            'archivedProducts',
            'statusFilter',
            'boostPrice',
            'boostPackages',
            'categories'
        ));
    }

    public function create()
    {
        if (request('type') === 'single') {
            $categories = Category::orderBy('name')->get();
            $boostPrice = self::DEFAULT_BOOST_PRICE;
            $units = self::BTP_UNITS;

            return view('vendor.products.products', compact('categories', 'boostPrice', 'units'));
        }

        return view('vendor.products.create_options');
    }

    public function store(Request $request)
    {
        $validated = $this->validateProductRequest($request, true);

        $user = Auth::user();
        $shop = $user->shop;

        if (! $shop) {
            return back()->with('error', 'Vous devez créer une boutique avant d\'ajouter un produit.');
        }

        $productData = $this->productDataFromValidated($validated, $request);
        $readiness = $this->shopPublicationReadiness($shop);
        $publicationBlocked = ! $readiness['can_publish'];

        $productData = array_merge($productData, [
            'shop_id' => $shop->id,
            'slug' => $this->generateUniqueSlug($validated['name']),
            'is_negotiable' => $request->has('is_negotiable')
                ? ($request->boolean('is_negotiable') && ! empty($validated['price_p1']) && ! empty($validated['price_p2']) && ! empty($validated['price_p3']))
                : (! empty($validated['price_p1']) && ! empty($validated['price_p2']) && ! empty($validated['price_p3'])),
            'is_active' => $publicationBlocked ? false : ! $request->boolean('save_as_draft'),
            'status' => $publicationBlocked ? 'pending_logistics' : ($request->boolean('save_as_draft') ? 'draft' : 'actif'),
            'is_boosted' => false,
            'boost_type' => null,
            'boost_start_at' => null,
            'boost_end_at' => null,
            'boost_price' => self::DEFAULT_BOOST_PRICE,
            'boost_payment_status' => null,
            'boost_payment_reference' => null,
            'boost_paid_at' => null,
        ]);

        if ($request->hasFile('technical_sheet')) {
            $productData['technical_sheet_path'] = $request->file('technical_sheet')
                ->store('products/technical-sheets', 'public');
        }

        if ($request->hasFile('product_video')) {
            $productData['product_video_path'] = $request->file('product_video')
                ->store('products/videos', 'public');
        }

        $product = Product::create($productData);

        $this->syncProductDeliveryZones($product, $request);
        $this->storeProductImages($product, $request);

        if ($request->expectsJson()) {
            // Les routes API mobiles (routes/api_vendor_mobile.php) n'ont pas de
            // middleware de session : flash() y ferait planter la création de
            // produit pour l'app mobile (RuntimeException "Session store not
            // set on request").
            if ($request->hasSession()) {
                $request->session()->flash($publicationBlocked ? 'warning' : 'success', $publicationBlocked
                    ? 'Produit enregistré mais non publié : ' . $readiness['reason']
                    : 'Produit ajouté avec succès.');
            }

            return response()->json(['redirect' => route('vendor.products', [], false)], 201);
        }

        return redirect()->route('vendor.products')
            ->with($publicationBlocked ? 'warning' : 'success', $publicationBlocked
                ? 'Produit enregistré mais non publié : ' . $readiness['reason']
                : 'Produit ajouté avec succès.');
    }

    public function edit(Product $product)
    {
        $this->ensureOwnsProduct($product);

        $product->load('images', 'category', 'deliveryZones');
        $categories = Category::orderBy('name')->get();
        $boostPrice = self::DEFAULT_BOOST_PRICE;
        $units = self::BTP_UNITS;

        return view('vendor.products.edit', compact('product', 'categories', 'boostPrice', 'units'));
    }

    public function update(Request $request, Product $product)
    {
        $this->ensureOwnsProduct($product);

        $validated = $this->validateProductRequest($request, false);
        $shop = Auth::user()?->shop;

        $productData = $this->productDataFromValidated($validated, $request);
        $readiness = $this->shopPublicationReadiness($shop);
        $publicationBlocked = ! $readiness['can_publish'];

        if ($publicationBlocked) {
            $productData['status'] = 'pending_logistics';
            $productData['is_active'] = false;
        } elseif ($request->boolean('save_as_draft')) {
            $productData['status'] = 'draft';
            $productData['is_active'] = false;
        } elseif (in_array($product->status, ['draft', 'inactif', 'inactive', 'pending_logistics', 'pending_publication'], true)) {
            // Lorsqu'un brouillon est publié depuis l'application mobile, il doit
            // réellement redevenir visible et actif après validation du formulaire.
            $productData['status'] = 'actif';
            $productData['is_active'] = true;
        }

        if ($request->hasFile('technical_sheet')) {
            if ($product->technical_sheet_path && Storage::disk('public')->exists($product->technical_sheet_path)) {
                Storage::disk('public')->delete($product->technical_sheet_path);
            }

            $productData['technical_sheet_path'] = $request->file('technical_sheet')
                ->store('products/technical-sheets', 'public');
        }

        if ($request->boolean('remove_product_video')) {
            if ($product->product_video_path && Storage::disk('public')->exists($product->product_video_path)) {
                Storage::disk('public')->delete($product->product_video_path);
            }
            $productData['product_video_path'] = null;
            $productData['product_video_url'] = null;
        }

        if ($request->hasFile('product_video')) {
            if ($product->product_video_path && Storage::disk('public')->exists($product->product_video_path)) {
                Storage::disk('public')->delete($product->product_video_path);
            }
            $productData['product_video_path'] = $request->file('product_video')
                ->store('products/videos', 'public');
        }

        $product->update($productData);

        $this->syncProductDeliveryZones($product, $request);

        if ($request->hasFile('images')) {
            $this->replaceProductImages($product, $request);
        } elseif ($request->filled('main_image')) {
            $product->images()->update(['is_main' => false]);

            $product->images()
                ->where('id', $request->main_image)
                ->where('product_id', $product->id)
                ->update(['is_main' => true]);

            $this->syncProductGalleryColumn($product);
        }

        return redirect()
            ->route('vendor.products.edit', $product)
            ->with($publicationBlocked ? 'warning' : 'success', $publicationBlocked
                ? 'Produit enregistré mais non publié : ' . $readiness['reason']
                : 'Produit mis à jour avec succès.');
    }

    public function payBoost(Request $request, Product $product, PayDunyaService $paydunya)
    {
        $this->ensureOwnsProduct($product);

        $shop = Auth::user()?->shop;
        $readiness = $this->shopPublicationReadiness($shop);
        $isPubliclyEligible = $readiness['can_publish']
            && ! $product->is_archived
            && (bool) $product->is_active
            && in_array($product->status, ['actif', 'active', 'approved'], true);

        if (! $isPubliclyEligible) {
            return response()->json([
                'success' => false,
                'message' => 'Le boost est réservé aux produits réellement publiables et actifs. ' . $readiness['reason'],
            ], 422);
        }

        $request->validate([
            'boost_package' => 'required|string',
            'phone' => 'nullable|string|max:30',
        ]);

        $package = $this->getBoostPackage($request->boost_package);

        $successUrl = route('vendor.products.boost.success', $product);
        $cancelUrl = route('vendor.products.boost.cancel', $product);

        $invoice = $paydunya->createBoostInvoice([
            'item_name' => 'Boost produit OVANIE',
            'description' => $package['label'] . ' - ' . $product->name,
            'amount' => $package['price'],
            'return_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        if ($invoice->create()) {
            $token = $invoice->token
                ?? $invoice->invoice_token
                ?? ($invoice->response_array['token'] ?? null)
                ?? ($invoice->response['token'] ?? null)
                ?? 'BOOST-' . strtoupper(uniqid());

            $product->update([
                'boost_type' => $package['type'],
                'boost_package' => $request->boost_package,
                'boost_duration_days' => $package['days'],
                'boost_priority' => $package['priority'],
                'boost_price' => $package['price'],
                'boost_payment_status' => 'pending',
                'boost_payment_reference' => $token,
            ]);

            return response()->json([
                'success' => true,
                'redirect_url' => $invoice->getInvoiceUrl(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $invoice->response_text ?? 'Impossible de créer le paiement.',
        ], 422);
    }

    public function boostSuccess(Product $product)
    {
        $this->ensureOwnsProduct($product);

        return redirect()
            ->route('vendor.products')
            ->with('success', 'Retour de paiement effectué. Validation finale en cours.');
    }

    public function boostCancel(Product $product)
    {
        $this->ensureOwnsProduct($product);

        $product->update([
            'boost_payment_status' => 'cancelled',
        ]);

        return redirect()
            ->route('vendor.products')
            ->with('error', 'Paiement annulé.');
    }

    public function paydunyaWebhook(Request $request)
    {
        $token = $request->input('data.token') ?? $request->input('token');

        if (! $token) {
            return response()->json(['error' => 'Token manquant'], 422);
        }

        $product = Product::where('boost_payment_reference', $token)->first();

        if (! $product) {
            return response()->json(['error' => 'Transaction introuvable'], 404);
        }

        if ($product->boost_payment_status === 'paid') {
            return response()->json(['success' => true, 'message' => 'Déjà traité']);
        }

        $days = $product->boost_duration_days ?: self::DEFAULT_BOOST_DAYS;

        $product->update([
            'is_boosted' => true,
            'boost_payment_status' => 'paid',
            'boost_paid_at' => now(),
            'boost_start_at' => now(),
            'boost_end_at' => now()->addDays($days),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Product $product)
    {
        $this->ensureOwnsProduct($product);

        if ($product->is_archived) {
            return $request->expectsJson()
                ? response()->json(['success' => true, 'message' => 'Ce produit est déjà archivé.'])
                : redirect()
                    ->route('vendor.products', ['status' => 'archived'])
                    ->with('info', 'Ce produit est déjà archivé.');
        }

        $hasOrderHistory = $product->orderItems()->exists();

        $product->update([
            'is_active' => false,
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => Auth::id(),
            'archive_reason' => $request->input(
                'archive_reason',
                $hasOrderHistory
                    ? 'Archivé par le vendeur. Historique de commande conservé.'
                    : 'Archivé par le vendeur.'
            ),
            'is_boosted' => false,
            'boost_end_at' => now(),
        ]);

        $message = $hasOrderHistory
            ? 'Produit archivé avec succès. Il n\'est plus visible à la vente, mais l\'historique des commandes est conservé.'
            : 'Produit archivé avec succès. Vous pouvez le restaurer depuis les archives.';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message])
            : redirect()
                ->route('vendor.products')
                ->with('success', $message);
    }

    public function restore(Product $product)
    {
        $this->ensureOwnsProduct($product);

        if (! $product->is_archived) {
            return redirect()
                ->route('vendor.products')
                ->with('info', 'Ce produit n\'est pas archivé.');
        }

        $shop = Auth::user()?->shop;
        $readiness = $this->shopPublicationReadiness($shop);

        $product->update([
            'is_active' => $readiness['can_publish'],
            'status' => $readiness['can_publish'] ? 'actif' : 'pending_logistics',
            'archived_at' => null,
            'archived_by' => null,
            'archive_reason' => null,
        ]);

        return redirect()
            ->route('vendor.products')
            ->with($readiness['can_publish'] ? 'success' : 'warning', $readiness['can_publish']
                ? 'Produit restauré et republié avec succès.'
                : 'Produit restauré dans votre espace, mais non publié : ' . $readiness['reason']);
    }

    public function toggleStatus(Request $request, Product $product)
    {
        $this->ensureOwnsProduct($product);

        if ($product->is_archived) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'activer un produit archivé. Restaurez-le d\'abord.',
            ], 422);
        }

        $shop = Auth::user()?->shop;
        $targetActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : ! (bool) $product->is_active;

        $readiness = $this->shopPublicationReadiness($shop);
        if ($targetActive && ! $readiness['can_publish']) {
            $product->update(['is_active' => false, 'status' => 'pending_logistics']);

            return response()->json([
                'success' => false,
                'message' => 'Activation impossible : ' . $readiness['reason'],
            ], 422);
        }

        $product->update([
            'is_active' => $targetActive,
            'status' => $targetActive ? 'actif' : 'inactif',
        ]);

        return response()->json([
            'success' => true,
            'is_active' => $product->is_active,
        ]);
    }

    public function export()
    {
        $shop = Auth::user()?->shop;

        if (! $shop) {
            abort(403);
        }

        $products = Product::with('category')
            ->where('shop_id', $shop->id)
            ->notArchived()
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="produits.csv"',
        ];

        $callback = function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'ID',
                'Nom',
                'Catégorie',
                'Prix',
                'Unité',
                'Quantité min.',
                'Stock',
                'Poids kg',
                'Volume m3',
                'Actif',
            ], ';');

            foreach ($products as $p) {
                fputcsv($handle, [
                    $p->id,
                    $p->name,
                    $p->category?->name ?? '',
                    $p->price,
                    $p->display_unit,
                    $p->min_order_quantity,
                    $p->stock,
                    $p->weight_kg,
                    $p->volume_m3,
                    $p->is_active ? 'Oui' : 'Non',
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function validateProductRequest(Request $request, bool $isCreate): array
    {
        $normalizedSaleType = str_replace(['_', '-'], ' ', mb_strtolower(trim((string) $request->input('sale_type', ''))));
        $isFlashSale = in_array($normalizedSaleType, ['flash', 'flash sale', 'vente flash'], true);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => [$isCreate ? 'required' : 'nullable', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'product_state' => ['nullable', Rule::in(['new', 'reconditioned', 'used'])],
            'sale_type' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:1'],
            'promo_price' => ['nullable', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'transport' => ['nullable', 'numeric', 'min:0'],
            'description' => ['required', 'string'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'technical_details' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:255'],

            'unit' => ['required', Rule::in(self::BTP_UNITS)],
            'unit_label' => ['nullable', 'string', 'max:80'],
            'min_order_quantity' => ['required', 'integer', 'min:1'],
            'packaging' => ['nullable', 'string', 'max:255'],
            'availability_status' => ['nullable', Rule::in(['in_stock', 'on_order', 'preorder', 'out_of_stock'])],
            'supply_delay' => ['nullable', 'string', 'max:100'],
            'content_per_unit' => ['nullable', 'numeric', 'min:0'],
            'content_unit' => ['nullable', 'string', 'max:50'],
            'units_per_package' => ['nullable', 'integer', 'min:0'],
            'coverage_per_unit_m2' => ['nullable', 'numeric', 'min:0'],
            'brand' => ['nullable', 'string', 'max:255'],
            'origin_country' => ['nullable', 'string', 'max:100'],
            'usage_area' => ['nullable', 'string', 'max:255'],
            'material_grade' => ['nullable', 'string', 'max:255'],
            'warranty' => ['nullable', 'string', 'max:255'],
            'return_policy' => ['nullable', 'string', 'max:255'],

            // Règles logistiques — obligatoires sauf en mode brouillon
            'weight_kg' => [(! $request->boolean('save_as_draft')) ? 'required' : 'nullable', 'numeric', 'min:0.01'],
            'length_cm' => [(! $request->boolean('save_as_draft')) ? 'required' : 'nullable', 'numeric', 'min:0.1'],
            'width_cm' => [(! $request->boolean('save_as_draft')) ? 'required' : 'nullable', 'numeric', 'min:0.1'],
            'height_cm' => [(! $request->boolean('save_as_draft')) ? 'required' : 'nullable', 'numeric', 'min:0.1'],
            'volume_m3' => ['nullable', 'numeric', 'min:0'],
            'delivery_mode' => ['nullable', Rule::in(['ovanie', 'seller'])],
            'is_negotiable' => ['nullable', 'boolean'],
            'fragile' => [(! $request->boolean('save_as_draft')) ? 'required' : 'nullable', 'boolean'],
            'requires_unloading' => [(! $request->boolean('save_as_draft')) ? 'required' : 'nullable', 'boolean'],
            'unloading_instructions' => [(! $request->boolean('save_as_draft') && $request->boolean('requires_unloading')) ? 'required' : 'nullable', 'string', 'max:255'],
            'handling_options' => ['nullable', 'array'],
            'handling_options.*' => ['nullable', 'string', 'max:80'],
            'pickup_city' => ['nullable', 'string', 'max:150'],
            'pickup_commune' => ['nullable', 'string', 'max:150'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'fast_delivery' => ['nullable', 'boolean'],
            'technical_sheet' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'product_video' => ['nullable', 'file', 'mimes:mp4,mov,webm,m4v', 'max:51200'],
            'product_video_url' => ['nullable', 'url', 'max:500'],
            'remove_product_video' => ['nullable', 'boolean'],
            'product_attributes' => ['nullable', 'array'],
            'product_attributes.*.label' => ['nullable', 'string', 'max:120'],
            'product_attributes.*.value' => ['nullable', 'string', 'max:255'],
            'product_attributes.*.unit' => ['nullable', 'string', 'max:50'],

            'images' => [($isCreate && ! $request->boolean('save_as_draft')) ? 'required' : 'nullable', 'array', ($isCreate && ! $request->boolean('save_as_draft')) ? 'min:1' : 'min:0', 'max:10'],
            'images.*' => [
                ($isCreate && ! $request->boolean('save_as_draft')) ? 'required' : 'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:' . (int) config('product-images.max_file_size_kb', 5120),
                new MarketplaceProductImage(),
            ],
            'main_image' => ['nullable', 'exists:product_images,id'],

            'price_p1' => ['nullable', 'required_if:is_negotiable,1', 'integer', 'min:1', 'lt:price'],
            'price_p2' => ['nullable', 'required_if:is_negotiable,1', 'integer', 'min:1', 'lt:price_p1'],
            'price_p3' => ['nullable', 'required_if:is_negotiable,1', 'integer', 'min:1', 'lt:price_p2'],
            'flash_start_at' => [$isFlashSale ? 'required' : 'nullable', 'date'],
            'flash_end' => [$isFlashSale ? 'required' : 'nullable', 'date', 'after:flash_start_at'],
            'bf_start' => ['nullable', 'date'],
            'bf_end' => ['nullable', 'date'],
        ], [
            'name.required' => 'Le nom du produit est obligatoire.',
            'category_id.required' => 'La catégorie est obligatoire.',
            'category_id.exists' => 'La catégorie sélectionnée est invalide.',
            'sale_type.required' => 'Le type de vente est obligatoire.',
            'flash_start_at.required' => 'Indiquez la date et l’heure de début de l’offre Flash.',
            'flash_end.required' => 'Indiquez la date et l’heure de fin de l’offre Flash.',
            'flash_end.after' => 'La fin de l’offre Flash doit être postérieure à son début.',
            'price.required' => 'Le prix est obligatoire.',
            'price.min' => 'Le prix doit être supérieur à 0.',
            'stock.required' => 'Le stock est obligatoire.',
            'description.required' => 'La description est obligatoire.',
            'unit.required' => 'L\'unité de vente est obligatoire.',
            'min_order_quantity.required' => 'La quantité minimum est obligatoire.',
            'images.required' => 'Ajoutez au moins une image produit.',
            'images.*.image' => 'Chaque fichier doit être une image valide.',
            'images.*.mimes' => 'Les images doivent être au format JPG, PNG ou WEBP.',
            'images.*.max' => 'Chaque image ne doit pas dépasser 5 Mo.',
            'technical_sheet.mimes' => 'La fiche technique doit être un fichier PDF.',
            'product_video.mimes' => 'La vidéo doit être au format MP4, MOV, WEBM ou M4V.',
            'product_video.max' => 'La vidéo produit ne doit pas dépasser 50 MB.',
            'price_p1.required_if' => 'La deuxième offre est obligatoire si le produit est négociable.',
            'price_p1.lt' => 'La deuxième offre doit être inférieure au prix normal.',
            'price_p2.required_if' => 'La troisième offre est obligatoire si le produit est négociable.',
            'price_p2.lt' => 'La troisième offre doit être inférieure à la deuxième offre.',
            'price_p3.required_if' => 'La dernière offre est obligatoire si le produit est négociable.',
            'price_p3.lt' => 'La dernière offre doit être inférieure à la troisième offre.',
            'product_video_url.url' => 'Le lien vidéo doit être une URL valide.',
            'weight_kg.required' => 'Le poids du produit est obligatoire.',
            'weight_kg.min' => 'Le poids doit être supérieur à 0.',
            'length_cm.required' => 'La longueur du produit est obligatoire.',
            'length_cm.min' => 'La longueur doit être supérieure à 0.',
            'width_cm.required' => 'La largeur du produit est obligatoire.',
            'width_cm.min' => 'La largeur doit être supérieure à 0.',
            'height_cm.required' => 'La hauteur du produit est obligatoire.',
            'height_cm.min' => 'La hauteur doit être supérieure à 0.',
            'fragile.required' => 'Indiquez si le produit est fragile (Oui/Non).',
            'requires_unloading.required' => 'Indiquez si le déchargement est requis (Oui/Non).',
            'unloading_instructions.required' => 'Les détails de déchargement sont obligatoires si le déchargement est requis.',
        ]);
    }

    private function productDataFromValidated(array $validated, Request $request): array
    {
        $volume = $validated['volume_m3'] ?? null;

        if (! $volume && $request->filled(['length_cm', 'width_cm', 'height_cm'])) {
            $volume = round(
                ((float) $request->length_cm * (float) $request->width_cm * (float) $request->height_cm) / 1000000,
                4
            );
        }

        $user = Auth::user();
        $shop = $user?->shop;
        $deliveryMode = ($shop && $shop->usesSellerLogistics()) ? 'seller' : 'ovanie';

        return [
            'category_id' => $validated['category_id'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'product_state' => $validated['product_state'] ?? 'new',
            'name' => $validated['name'],
            'sale_type' => $validated['sale_type'],
            'description' => $validated['description'],
            'short_description' => $validated['short_description'] ?? null,
            'technical_details' => $validated['technical_details'] ?? null,
            'price' => $validated['price'],
            'promo_price' => $validated['promo_price'] ?? null,
            'stock' => $validated['stock'],
            'type' => $validated['type'] ?? null,
            'transport' => $validated['transport'] ?? null,
            'price_p1' => ! empty($validated['price_p3']) ? ($validated['price_p1'] ?? null) : null,
            'price_p2' => ! empty($validated['price_p3']) ? ($validated['price_p2'] ?? null) : null,
            'price_p3' => $validated['price_p3'] ?? null,
            'flash_start_at' => $validated['flash_start_at'] ?? null,
            'flash_end' => $validated['flash_end'] ?? null,
            'bf_start' => $validated['bf_start'] ?? null,
            'bf_end' => $validated['bf_end'] ?? null,
            'unit' => $validated['unit'],
            'unit_label' => $validated['unit_label'] ?? null,
            'min_order_quantity' => $validated['min_order_quantity'],
            'packaging' => $validated['packaging'] ?? null,
            'availability_status' => $validated['availability_status'] ?? (($validated['stock'] ?? 0) > 0 ? 'in_stock' : 'on_order'),
            'supply_delay' => $validated['supply_delay'] ?? null,
            'content_per_unit' => $validated['content_per_unit'] ?? null,
            'content_unit' => $validated['content_unit'] ?? null,
            'units_per_package' => $validated['units_per_package'] ?? null,
            'coverage_per_unit_m2' => $validated['coverage_per_unit_m2'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'origin_country' => $validated['origin_country'] ?? null,
            'usage_area' => $validated['usage_area'] ?? null,
            'material_grade' => $validated['material_grade'] ?? null,
            'warranty' => $validated['warranty'] ?? null,
            'return_policy' => $validated['return_policy'] ?? null,
            'weight_kg' => $validated['weight_kg'] ?? null,
            'length_cm' => $validated['length_cm'] ?? null,
            'width_cm' => $validated['width_cm'] ?? null,
            'height_cm' => $validated['height_cm'] ?? null,
            'volume_m3' => $volume,
            'delivery_mode' => $deliveryMode,
            'seller_delivery_delay' => null,
            'is_negotiable' => $request->has('is_negotiable')
                ? ($request->boolean('is_negotiable') && ! empty($validated['price_p1']) && ! empty($validated['price_p2']) && ! empty($validated['price_p3']))
                : (! empty($validated['price_p1']) && ! empty($validated['price_p2']) && ! empty($validated['price_p3'])),
            'pickup_city' => $validated['pickup_city'] ?? null,
            'pickup_commune' => $validated['pickup_commune'] ?? null,
            'pickup_address' => $validated['pickup_address'] ?? null,
            'product_attributes' => $this->cleanProductAttributes($request->input('product_attributes', [])),
            'handling_options' => $this->cleanList($request->input('handling_options', [])),
            'product_video_url' => $validated['product_video_url'] ?? null,
            'fragile' => $request->has('fragile') ? $request->boolean('fragile') : false,
            'requires_unloading' => $request->has('requires_unloading') ? $request->boolean('requires_unloading') : false,
            'unloading_instructions' => $request->boolean('requires_unloading') ? ($validated['unloading_instructions'] ?? null) : null,
            'fast_delivery' => $request->boolean('fast_delivery'),
        ];
    }

    private function cleanProductAttributes(array $attributes): array
    {
        return collect($attributes)
            ->map(function ($attribute) {
                return [
                    'label' => trim((string) ($attribute['label'] ?? '')),
                    'value' => trim((string) ($attribute['value'] ?? '')),
                    'unit' => trim((string) ($attribute['unit'] ?? '')),
                ];
            })
            ->filter(fn ($attribute) => $attribute['label'] !== '' && $attribute['value'] !== '')
            ->values()
            ->all();
    }

    private function cleanList(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function storeProductImages(
        Product $product,
        Request $request,
        bool $setFirstAsMain = true
    ): void {
        if (! $request->hasFile('images')) {
            $this->syncProductGalleryColumn($product);
            return;
        }

        $hasMainImage = $product->images()->where('is_main', true)->exists();
        $payloads = $this->attachProductIdToPayloads(
            $product,
            $this->normalizeProductImageUploads(
                $request,
                $setFirstAsMain || ! $hasMainImage
            )
        );

        try {
            foreach ($payloads as $payload) {
                ProductImage::create($payload);
            }
        } catch (Throwable $exception) {
            $this->deleteGeneratedImagePayloads($payloads);
            throw $exception;
        }

        $this->syncProductGalleryColumn($product);
    }

    private function replaceProductImages(
        Product $product,
        Request $request
    ): void {
        $payloads = $this->attachProductIdToPayloads(
            $product,
            $this->normalizeProductImageUploads($request, true)
        );
        $oldImages = $product->images()->get();

        try {
            DB::transaction(function () use ($product, $payloads) {
                $product->images()->delete();

                foreach ($payloads as $payload) {
                    ProductImage::create($payload);
                }
            });
        } catch (Throwable $exception) {
            $this->deleteGeneratedImagePayloads($payloads);
            throw $exception;
        }

        $this->deleteProductImageFiles($oldImages);
        $this->syncProductGalleryColumn($product);
    }

    /** @return array<int,array<string,mixed>> */
    private function normalizeProductImageUploads(
        Request $request,
        bool $firstAsMain
    ): array {
        $normalizer = app(ProductImageNormalizer::class);
        $payloads = [];
        $position = 0;

        foreach ((array) $request->file('images', []) as $imageFile) {
            if (! $imageFile || ! $imageFile->isValid()) {
                continue;
            }

            try {
                $result = $normalizer->normalize($imageFile, 'products');
            } catch (Throwable $exception) {
                $this->deleteGeneratedImagePayloads($payloads);

                throw ValidationException::withMessages([
                    'images' => 'L’image « '
                        . $imageFile->getClientOriginalName()
                        . ' » ne respecte pas le standard OVANIE : '
                        . $exception->getMessage(),
                ]);
            }

            $isMain = $firstAsMain && $position === 0;
            $payload = [
                'product_id' => null,
                'path' => $result['master'],
                'original_path' => $result['original'],
                'card_path' => $result['card'],
                'thumb_path' => $result['thumb'],
                'original_width' => $result['original_width'],
                'original_height' => $result['original_height'],
                'normalized_at' => now(),
                'is_main' => $isMain,
            ];

            if (Schema::hasColumn('product_images', 'is_primary')) {
                $payload['is_primary'] = $isMain;
            }

            if (Schema::hasColumn('product_images', 'sort_order')) {
                $payload['sort_order'] = $position;
            }

            $payloads[] = $payload;
            $position++;
        }

        if ($payloads === []) {
            throw ValidationException::withMessages([
                'images' => 'Aucune image produit valide n’a été reçue.',
            ]);
        }

        return $payloads;
    }

    private function attachProductIdToPayloads(
        Product $product,
        array $payloads
    ): array {
        return array_map(function (array $payload) use ($product) {
            $payload['product_id'] = $product->id;
            return $payload;
        }, $payloads);
    }

    private function deleteGeneratedImagePayloads(array $payloads): void
    {
        $paths = [];

        foreach ($payloads as $payload) {
            foreach (['original_path', 'path', 'card_path', 'thumb_path'] as $field) {
                if (! empty($payload[$field])) {
                    $paths[] = (string) $payload[$field];
                }
            }
        }

        $this->deleteStoragePaths($paths);
    }

    private function deleteProductImageFiles(iterable $images): void
    {
        $paths = [];

        foreach ($images as $image) {
            foreach ([
                'original_path',
                'path',
                'card_path',
                'thumb_path',
                'image_path',
                'file_path',
                'filename',
            ] as $field) {
                if (! blank($image->{$field} ?? null)) {
                    $paths[] = (string) $image->{$field};
                }
            }
        }

        $this->deleteStoragePaths($paths);
    }

    private function deleteStoragePaths(array $paths): void
    {
        $disk = Storage::disk((string) config('product-images.disk', 'public'));

        collect($paths)
            ->filter()
            ->map(function (string $path): string {
                $path = trim(str_replace('\\', '/', $path));
                $path = preg_replace('#^(https?:)?//[^/]+/storage/#i', '', $path) ?: $path;
                $path = preg_replace('#^/?storage/#', '', $path) ?: $path;
                $path = preg_replace('#^/?public/#', '', $path) ?: $path;
                return ltrim($path, '/');
            })
            ->filter()
            ->unique()
            ->each(function (string $path) use ($disk) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            });
    }

    private function syncProductGalleryColumn(Product $product): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('products', 'gallery')) {
            return;
        }

        $paths = $product->images()
            ->orderByDesc('is_main')
            ->orderBy('id')
            ->pluck('path')
            ->filter()
            ->values()
            ->all();

        $product->forceFill(['gallery' => $paths ?: null])->saveQuietly();
    }

    private function syncProductDeliveryZones(Product $product, Request $request): void
    {
        $product->deliveryZones()->delete();
    }

    private function shopPublicationReadiness(?Shop $shop): array
    {
        if (! $shop) {
            return [
                'can_publish' => false,
                'reason' => 'aucune boutique active n’est associée à ce compte.',
            ];
        }

        $validator = app(SellerLogisticsValidator::class);
        $validation = $validator->validate($shop);

        if ($shop->logistics_status !== $validation['status']) {
            $shop = $validator->synchronizeShopStatus($shop);
        } else {
            $shop->refresh();
        }

        if ($shop->status !== Shop::STATUS_APPROVED || ! $shop->is_active) {
            return [
                'can_publish' => false,
                'reason' => 'la boutique n’est pas actuellement ouverte à la publication.',
            ];
        }

        if (! $validation['complete'] || ! $shop->canPublishProducts()) {
            $missing = implode(', ', $validation['missing'] ?? []);

            return [
                'can_publish' => false,
                'reason' => $missing !== ''
                    ? 'configuration logistique incomplète (' . $missing . ').'
                    : 'configuration logistique incomplète.',
            ];
        }

        return [
            'can_publish' => true,
            'reason' => 'la boutique et sa logistique sont prêtes.',
        ];
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'produit';
        }

        $slug = $base;
        $counter = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
