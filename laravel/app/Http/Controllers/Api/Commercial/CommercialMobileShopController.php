<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CommercialProspect;
use App\Models\CommercialProspectingMission;
use App\Models\CommercialProspectingMissionQuarter;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use App\Services\CommercialShopLocationService;
use App\Services\Geo\GeocodingService;
use App\Services\OvanieReferenceDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CommercialMobileShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $commercial */
        $commercial = $request->user();
        $search = trim((string) $request->query('q', ''));
        $filter = Str::lower(trim((string) $request->query('status', 'all')));

        $base = $this->shopsForCommercial((int) $commercial->id);

        $summary = [
            'all' => (clone $base)->count(),
            'online' => $this->statusQuery(clone $base, 'online')->count(),
            'pending' => $this->statusQuery(clone $base, 'pending')->count(),
            'suspended' => $this->statusQuery(clone $base, 'suspended')->count(),
        ];

        $query = clone $base;
        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->where('shops.name', 'like', $needle)
                    ->when(Schema::hasColumn('shops', 'display_name'), fn (Builder $q) => $q->orWhere('shops.display_name', 'like', $needle))
                    ->orWhere('shops.city', 'like', $needle)
                    ->orWhere('shops.commune', 'like', $needle)
                    ->orWhereHas('user', function (Builder $user) use ($needle): void {
                        $user
                            ->where('name', 'like', $needle)
                            ->orWhere('first_name', 'like', $needle)
                            ->orWhere('last_name', 'like', $needle)
                            ->orWhere('email', 'like', $needle)
                            ->orWhere('phone', 'like', $needle);
                    });
            });
        }

        if (in_array($filter, ['online', 'pending', 'suspended'], true)) {
            $query = $this->statusQuery($query, $filter);
        }

        $shops = $query
            ->with('user:id,name,first_name,last_name,email,phone')
            ->withCount('products')
            ->latest('shops.created_at')
            ->limit(100)
            ->get();

        $metrics = $this->shopMetrics($shops->pluck('id')->map(fn ($id) => (int) $id)->all());

        return response()->json([
            'profile' => $this->profile($request, $commercial),
            'unread_notifications' => $commercial->unreadNotifications()->count(),
            'summary' => $summary,
            'shops' => $shops->map(function (Shop $shop) use ($request, $metrics) {
                $row = $metrics[(int) $shop->id] ?? ['orders_count' => 0, 'sales_30d' => 0.0];
                return $this->serializeShop(
                    $request,
                    $shop,
                    (int) ($shop->getAttribute('products_count') ?? 0),
                    (int) $row['orders_count'],
                    (float) $row['sales_30d'],
                );
            })->values(),
        ]);
    }

    public function reverseGeocode(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $geo = $geocoding->reverse((float) $data['latitude'], (float) $data['longitude']);
        $resolved = (array) ($geo['resolved_location'] ?? []);

        return response()->json([
            'address' => $geo['display_name'] ?? null,
            'commune' => $resolved['commune'] ?? null,
            'quarter' => $resolved['quartier'] ?? null,
            'landmark' => $resolved['repere'] ?? null,
            'city' => $resolved['city'] ?? null,
        ]);
    }

    public function meta(Request $request, OvanieReferenceDataService $references): JsonResponse
    {
        return response()->json([
            'categories' => $references->productCategories(),
            'communes' => $references->communes(true),
            'regions' => $references->regions(),
            'cities' => $references->cities(),
            'identity_countries' => $references->identityCountries(),
            'identity_types' => $references->valueOptions('identity_types'),
            'payment_modes' => $references->valueOptions('vendor_payment_modes'),
            'payout_methods' => $references->payoutMethods(),
            'logistics_types' => $references->valueOptions('logistics_types'),
            'delivery_zones' => $references->valueOptions('delivery_zones'),
        ]);
    }

    public function show(Request $request, Shop $shop): JsonResponse
    {
        /** @var User $commercial */
        $commercial = $request->user();
        abort_unless($this->shopBelongsToCommercial($shop, (int) $commercial->id), 404);

        $shop->load('user:id,name,first_name,last_name,email,phone');

        $products = Product::query()
            ->where('shop_id', $shop->id)
            ->with(['category:id,name,slug', 'images'])
            ->when(Schema::hasColumn('products', 'archived_at'), fn (Builder $q) => $q->whereNull('archived_at'))
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $productIds = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allProductIds = Product::query()->where('shop_id', $shop->id)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $reviewsCount = 0;
        $rating = 0.0;
        if ($allProductIds !== []) {
            $reviews = Review::query()->whereIn('product_id', $allProductIds);
            $reviewsCount = (clone $reviews)->count();
            $rating = round((float) ((clone $reviews)->avg('rating') ?? 0), 1);
        }

        $categories = Product::query()
            ->where('shop_id', $shop->id)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as products_count')
            ->groupBy('category_id')
            ->orderByDesc('products_count')
            ->limit(8)
            ->get()
            ->map(function ($row) use ($request, $shop) {
                $category = Category::find($row->category_id);
                if (! $category) {
                    return null;
                }
                $sample = Product::query()
                    ->where('shop_id', $shop->id)
                    ->where('category_id', $category->id)
                    ->with('images')
                    ->latest('id')
                    ->first();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'products_count' => (int) $row->products_count,
                    'image_url' => $sample ? $this->productImageUrl($request, $sample) : null,
                ];
            })
            ->filter()
            ->values();

        $metrics = $this->shopMetrics([(int) $shop->id]);
        $metric = $metrics[(int) $shop->id] ?? ['orders_count' => 0, 'sales_30d' => 0.0];
        $productsCount = Product::query()->where('shop_id', $shop->id)->count();

        return response()->json([
            'shop' => $this->serializeShop(
                $request,
                $shop,
                $productsCount,
                (int) $metric['orders_count'],
                (float) $metric['sales_30d'],
            ),
            'description' => (string) ($shop->description ?? ''),
            'rating' => $rating,
            'reviews_count' => $reviewsCount,
            'followers_count' => 0,
            'logistics_label' => $shop->logistics_mode_label,
            'payout_label' => $this->payoutMethodLabel((string) $shop->mm_operator),
            'owner_phone' => (string) ($shop->user?->phone ?? ''),
            'owner_email' => (string) ($shop->user?->email ?? ''),
            'categories' => $categories,
            'products' => $products->map(function (Product $product) use ($request) {
                $basePrice = (float) ($product->price ?? 0);
                $finalPrice = (float) ($product->final_price ?? $basePrice);
                $discount = $basePrice > 0 && $finalPrice < $basePrice
                    ? (int) round((($basePrice - $finalPrice) / $basePrice) * 100)
                    : 0;

                return [
                    'id' => $product->id,
                    'name' => (string) $product->name,
                    'price' => $finalPrice,
                    'old_price' => $discount > 0 ? $basePrice : 0,
                    'discount_percent' => $discount,
                    'image_url' => $this->productImageUrl($request, $product),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, CommercialShopLocationService $locations): JsonResponse
    {
        /** @var User $commercial */
        $commercial = $request->user();

        $data = $request->validate([
            'prospect_id' => ['nullable', 'integer', 'exists:commercial_prospects,id'],
            'prospecting_mission_id' => ['nullable', 'integer', 'exists:commercial_prospecting_missions,id'],
            'prospecting_quarter_id' => ['nullable', 'integer', 'required_with:prospecting_mission_id', 'exists:abidjan_quarters,id'],
            'seller_type' => ['required', Rule::in(['particulier', 'entreprise'])],
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone_country' => ['required', 'string', 'max:8'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp_country' => ['nullable', 'string', 'max:8'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'responsible_notes' => ['nullable', 'string', 'max:2000'],
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'shop_name' => ['required', 'string', 'min:2', 'max:160'],
            'display_name' => ['nullable', 'string', 'max:160'],
            'main_category' => ['required', 'string', 'max:160'],
            'main_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'main_subcategory' => ['nullable', 'string', 'max:160'],
            'main_subcategory_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['required', 'string', 'max:500'],
            'shop_notes' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'region' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'commune' => ['required', 'string', 'max:160'],
            'commune_id' => ['nullable', 'integer', 'exists:abidjan_communes,id'],
            'district' => ['required', 'string', 'max:160'],
            'quarter_id' => ['nullable', 'integer', 'exists:abidjan_quarters,id'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_if:logistics_type,ovanie'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_if:logistics_type,ovanie'],
            'geo_accuracy' => ['nullable', 'numeric', 'min:0'],
            'geo_source' => ['nullable', Rule::in(['browser_gps', 'manual_map'])],
            'logistics_type' => ['required', Rule::in(['ovanie', 'seller'])],
            'delivery_zone' => ['required', Rule::in(['abidjan', 'grand_abidjan', 'national', 'afrique_ouest'])],
            'delivery_zone_text' => ['nullable', 'string', 'max:255'],
            'location_notes' => ['nullable', 'string', 'max:2000'],

            'identity_country' => ['required', Rule::in(['ci'])],
            'identity_type' => ['required', Rule::in(['cni', 'passport', 'permis', 'resident'])],
            'identity_number' => ['required', 'string', 'max:100'],
            'identity_file_front' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'identity_file_back' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'identity_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'identity_notes' => ['nullable', 'string', 'max:2000'],

            'payment_mode' => ['required', Rule::in([Shop::PAYMENT_POST_DELIVERY, Shop::PAYMENT_WEEKLY])],
            'payout_method' => ['required', Rule::in(['orange', 'mtn', 'moov', 'wave', 'bank'])],
            'payout_holder' => ['required', 'string', 'max:160'],
            'payout_number' => ['required', 'string', 'max:120'],
            'payout_confirmed' => ['accepted'],
            'payout_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'selfie.required' => 'Ajoutez la photo ou le selfie du responsable.',
            'latitude.required_if' => 'Enregistrez la position GPS pour OVANIE Logistics.',
            'longitude.required_if' => 'Enregistrez la position GPS pour OVANIE Logistics.',
        ]);

        if (! empty($data['prospect_id'])) {
            $prospect = CommercialProspect::query()->find((int) $data['prospect_id']);
            if ($prospect?->shop_id) {
                return response()->json(['message' => 'Ce vendeur potentiel est déjà lié à une boutique OVANIE.'], 409);
            }
        }

        $prospectingMission = null;
        $prospectingMissionQuarter = null;
        if (! empty($data['prospecting_mission_id'])) {
            $today = now()->toDateString();
            $prospectingMission = CommercialProspectingMission::query()
                ->forCommercial((int) $commercial->id)
                ->whereKey((int) $data['prospecting_mission_id'])
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereDate('starts_on', '<=', $today)
                ->whereDate('ends_on', '>=', $today)
                ->first();

            if (! $prospectingMission) {
                return response()->json(['message' => 'Cette mission de prospection ne vous est pas affectée ou n’est pas active aujourd’hui.'], 403);
            }

            $prospectingMissionQuarter = CommercialProspectingMissionQuarter::query()
                ->where('mission_id', $prospectingMission->id)
                ->where('quarter_id', (int) $data['prospecting_quarter_id'])
                ->first();

            if (! $prospectingMissionQuarter) {
                return response()->json(['message' => 'Ce quartier ne fait pas partie de votre mission de prospection.'], 422);
            }
            if ($prospectingMissionQuarter->status === 'completed') {
                return response()->json(['message' => 'Ce quartier est déjà clôturé pour cette mission.'], 422);
            }

            if ((int) ($data['commune_id'] ?? 0) !== (int) $prospectingMission->commune_id
                || (int) ($data['quarter_id'] ?? 0) !== (int) $prospectingMissionQuarter->quarter_id) {
                return response()->json([
                    'message' => 'La boutique doit être enregistrée dans la commune et le quartier affectés à votre mission.',
                ], 422);
            }
        }

        $phone = $this->normalizePhone((string) $data['phone_country'], (string) $data['phone']);
        if (User::query()->where('phone', $phone)->exists()) {
            return response()->json(['message' => 'Ce numéro de téléphone est déjà utilisé.', 'errors' => ['phone' => ['Ce numéro de téléphone est déjà utilisé.']]], 422);
        }

        if (Shop::query()->where('identity_number', trim((string) $data['identity_number']))->exists()) {
            return response()->json(['message' => 'Ce numéro de pièce est déjà associé à une boutique.', 'errors' => ['identity_number' => ['Ce numéro de pièce est déjà associé à une boutique.']]], 422);
        }

        if (! $request->hasFile('identity_file')) {
            if (! $request->hasFile('identity_file_front') || ! $request->hasFile('identity_file_back')) {
                return response()->json([
                    'message' => 'Ajoutez le recto et le verso de la pièce, ou importez un document PDF.',
                    'errors' => ['identity_file_front' => ['Recto/verso ou PDF requis.']],
                ], 422);
            }
        }

        $fullName = trim(preg_replace('/\s+/', ' ', (string) $data['full_name']) ?? '');
        $parts = preg_split('/\s+/', $fullName) ?: [];
        $firstName = array_shift($parts) ?: $fullName;
        $lastName = trim(implode(' ', $parts));
        $whatsapp = filled($data['whatsapp'] ?? null)
            ? $this->normalizePhone((string) ($data['whatsapp_country'] ?? $data['phone_country']), (string) $data['whatsapp'])
            : $phone;

        [$vendor, $shop] = DB::transaction(function () use (
            $request,
            $commercial,
            $data,
            $fullName,
            $firstName,
            $lastName,
            $phone,
            $whatsapp,
            $locations,
            $prospectingMission,
            $prospectingMissionQuarter,
        ) {
            /** @var User $vendor */
            $vendor = User::create([
                'created_by_commercial_id' => $commercial->id,
                'first_name' => $firstName,
                'last_name' => $lastName !== '' ? $lastName : null,
                'name' => $fullName,
                'email' => Str::lower(trim((string) $data['email'])),
                'phone' => $phone,
                'whatsapp_phone' => $whatsapp,
                'password' => Hash::make((string) $data['password']),
                'role' => 'vendor',
                'status' => 'active',
                'city' => trim((string) $data['city']),
                'account_type' => (string) $data['seller_type'],
            ]);

            $selfie = $request->file('selfie')?->store('kyc/selfies', 'local');
            $logo = $request->file('logo')?->store('shops/logos', 'public');
            $identityFront = $request->file('identity_file_front')?->store('kyc/identity', 'local');
            $identityBack = $request->file('identity_file_back')?->store('kyc/identity', 'local');
            $identityPdf = $request->file('identity_file')?->store('kyc/identity', 'local');

            $locationPayload = $locations->creationPayload([
                'logistics_type' => (string) $data['logistics_type'],
                'location_mode' => (string) $data['logistics_type'] === 'ovanie' ? 'gps_now' : 'later',
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'geo_accuracy' => $data['geo_accuracy'] ?? null,
                'geo_source' => $data['geo_source'] ?? 'browser_gps',
            ]);

            // Même en logistique vendeur, conserver la position terrain lorsqu'elle a été capturée.
            // Le statut logistique reste néanmoins incomplet tant que la configuration vendeur n'est pas renseignée.
            if ((string) $data['logistics_type'] === 'seller' && isset($data['latitude'], $data['longitude'])) {
                $locationPayload['latitude'] = round((float) $data['latitude'], 7);
                $locationPayload['longitude'] = round((float) $data['longitude'], 7);
                $locationPayload['geo_accuracy'] = isset($data['geo_accuracy']) ? round((float) $data['geo_accuracy'], 2) : null;
                $locationPayload['geo_source'] = (string) ($data['geo_source'] ?? 'browser_gps');
                $locationPayload['geo_precision'] = $locationPayload['geo_source'] === 'manual_map' ? 'manual' : 'unknown';
                $locationPayload['geo_precision_score'] = $locationPayload['geo_source'] === 'manual_map' ? 85 : 70;
                $locationPayload['geo_status'] = $locationPayload['geo_source'] === 'manual_map'
                    ? Shop::GEO_STATUS_RELIABLE
                    : Shop::GEO_STATUS_REVIEW_RECOMMENDED;
                $locationPayload['geo_verified_at'] = $locationPayload['geo_source'] === 'manual_map' ? now() : null;
                $locationPayload['logistics_status'] = Shop::LOGISTICS_INCOMPLETE;
            }

            $slug = Shop::query()->where('slug', Str::slug((string) $data['shop_name']))->exists()
                ? Str::slug((string) $data['shop_name']).'-'.Str::lower(Str::random(5))
                : Str::slug((string) $data['shop_name']);

            $shopData = [
                'user_id' => $vendor->id,
                'created_by_commercial_id' => $commercial->id,
                'managed_by_commercial_id' => $commercial->id,
                'name' => trim((string) $data['shop_name']),
                'slug' => $slug !== '' ? $slug : 'boutique-'.Str::lower(Str::random(8)),
                'description' => trim((string) $data['description']),
                'logo' => $logo,
                'selfie' => $selfie,
                'seller_type' => (string) $data['seller_type'],
                'main_category' => trim((string) $data['main_category']),
                'delivery_zone' => (string) $data['delivery_zone'],
                'city' => trim((string) $data['city']),
                'region' => trim((string) $data['region']),
                'commune' => trim((string) $data['commune']),
                'commune_id' => $data['commune_id'] ?? null,
                'district' => trim((string) $data['district']),
                'quarter_id' => $data['quarter_id'] ?? null,
                'landmark' => filled($data['landmark'] ?? null) ? trim((string) $data['landmark']) : null,
                'address' => trim((string) $data['address']),
                'whatsapp' => $whatsapp,
                'business_email' => Str::lower(trim((string) $data['email'])),
                'identity_country' => (string) $data['identity_country'],
                'identity_type' => (string) $data['identity_type'],
                'identity_number' => trim((string) $data['identity_number']),
                'identity_upload_mode' => $identityPdf ? 'pdf' : 'images',
                'identity_file' => $identityPdf,
                'identity_file_front' => $identityFront,
                'identity_file_back' => $identityBack,
                'mm_operator' => (string) $data['payout_method'],
                'mm_number' => trim((string) $data['payout_number']),
                'mm_holder' => trim((string) $data['payout_holder']),
                'direct_payment' => false,
                'logistics_type' => (string) $data['logistics_type'],
                'payment_mode' => (string) $data['payment_mode'],
                'status' => Shop::STATUS_APPROVED,
                'kyc_status' => Shop::KYC_PENDING,
                'is_active' => true,
                'approved_at' => now(),
                ...$locationPayload,
            ];

            if (Schema::hasColumn('shops', 'display_name')) {
                $shopData['display_name'] = trim((string) ($data['display_name'] ?: $data['shop_name']));
            }
            if (Schema::hasColumn('shops', 'main_subcategory')) {
                $shopData['main_subcategory'] = trim((string) ($data['main_subcategory'] ?? '')) ?: null;
            }
            if (Schema::hasColumn('shops', 'commercial_notes')) {
                $shopData['commercial_notes'] = json_encode([
                    'responsable' => trim((string) ($data['responsible_notes'] ?? '')),
                    'boutique' => trim((string) ($data['shop_notes'] ?? '')),
                    'localisation' => trim((string) ($data['location_notes'] ?? '')),
                    'identite' => trim((string) ($data['identity_notes'] ?? '')),
                    'reversement' => trim((string) ($data['payout_notes'] ?? '')),
                    'delivery_zone_text' => trim((string) ($data['delivery_zone_text'] ?? '')),
                    'main_category_id' => $data['main_category_id'] ?? null,
                    'main_subcategory_id' => $data['main_subcategory_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            /** @var Shop $shop */
            $shop = Shop::create($shopData);

            if ($prospectingMissionQuarter && $prospectingMissionQuarter->status === 'pending') {
                $prospectingMissionQuarter->update([
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'updated_by_commercial_id' => $commercial->id,
                ]);
            }

            // Lorsqu'une boutique est ouverte directement depuis une mission,
            // créer automatiquement le suivi de prospection. Le commercial
            // n'a donc plus à saisir une fiche "vendeur potentiel" avant
            // d'ouvrir la boutique.
            if (empty($data['prospect_id']) && $prospectingMission && $prospectingMissionQuarter) {
                CommercialProspect::create([
                    'mission_id' => $prospectingMission->id,
                    'area_id' => null,
                    'commune_id' => $prospectingMission->commune_id,
                    'quarter_id' => $prospectingMissionQuarter->quarter_id,
                    'locality_id' => null,
                    'discovered_by_commercial_id' => $commercial->id,
                    'vendor_user_id' => $vendor->id,
                    'shop_id' => $shop->id,
                    'business_name' => $shop->name,
                    'category' => trim((string) ($data['main_category'] ?? '')) ?: 'Vendeur BTP',
                    'contact_name' => $fullName,
                    'phone' => $phone,
                    'whatsapp' => $whatsapp,
                    'address' => trim((string) $data['address']),
                    'landmark' => filled($data['landmark'] ?? null) ? trim((string) $data['landmark']) : null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'status' => 'shop_opened',
                    'potential' => 'high',
                    'notes' => 'Boutique ouverte directement depuis la mission de prospection.',
                    'last_visited_at' => now(),
                    'next_follow_up_at' => null,
                    'converted_at' => now(),
                ]);
            }

            return [$vendor, $shop];
        });

        if (! empty($data['prospect_id'])) {
            CommercialProspect::query()->whereKey((int) $data['prospect_id'])->update([
                'vendor_user_id' => $vendor->id,
                'shop_id' => $shop->id,
                'status' => 'shop_opened',
                'converted_at' => now(),
                'next_follow_up_at' => null,
            ]);
        }

        $shop->load('user:id,name,first_name,last_name,email,phone');

        return response()->json([
            'message' => 'Le vendeur et sa boutique ont été enregistrés avec succès.',
            'shop' => $this->serializeShop($request, $shop, 0, 0, 0.0),
        ], 201);
    }

    private function shopsForCommercial(int $commercialId): Builder
    {
        return Shop::query()->where(function (Builder $query) use ($commercialId): void {
            $query
                ->where('created_by_commercial_id', $commercialId)
                ->orWhere('managed_by_commercial_id', $commercialId)
                ->orWhereHas('user', fn (Builder $user) => $user->where('created_by_commercial_id', $commercialId));
        });
    }

    private function shopBelongsToCommercial(Shop $shop, int $commercialId): bool
    {
        if ((int) $shop->created_by_commercial_id === $commercialId || (int) $shop->managed_by_commercial_id === $commercialId) {
            return true;
        }

        return User::query()
            ->whereKey($shop->user_id)
            ->where('created_by_commercial_id', $commercialId)
            ->exists();
    }

    private function statusQuery(Builder $query, string $status): Builder
    {
        return match ($status) {
            'online' => $query->where('status', Shop::STATUS_APPROVED)
                ->where('is_active', true)
                ->where(function (Builder $q): void {
                    $q->whereNull('logistics_status')
                        ->orWhereNotIn('logistics_status', [Shop::LOGISTICS_INCOMPLETE, Shop::LOGISTICS_SUSPENDED]);
                }),
            'suspended' => $query->where(function (Builder $q): void {
                $q->where('is_active', false)
                    ->orWhere('status', Shop::STATUS_REJECTED)
                    ->orWhere('logistics_status', Shop::LOGISTICS_SUSPENDED);
            }),
            'pending' => $query->where(function (Builder $q): void {
                $q->where('status', 'pending')
                    ->orWhere(function (Builder $approved): void {
                        $approved->where('status', Shop::STATUS_APPROVED)
                            ->where('is_active', true)
                            ->where('logistics_status', Shop::LOGISTICS_INCOMPLETE);
                    });
            }),
            default => $query,
        };
    }

    /**
     * @param array<int, int> $shopIds
     * @return array<int, array{orders_count:int,sales_30d:float}>
     */
    private function shopMetrics(array $shopIds): array
    {
        if ($shopIds === []) {
            return [];
        }

        $orders = OrderItem::query()
            ->whereIn('order_items.shop_id', $shopIds)
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where(function ($query): void {
                $query->where('orders.payment_method', '!=', 'paydunya')
                    ->orWhereNull('orders.payment_method')
                    ->orWhereIn('orders.payment_status', ['paid', 'commission_paid', 'partial', 'escrow_held', 'verified']);
            })
            ->selectRaw('order_items.shop_id, COUNT(DISTINCT order_items.order_id) as orders_count')
            ->groupBy('order_items.shop_id')
            ->get()
            ->keyBy('shop_id');

        $sales = OrderItem::query()
            ->whereIn('order_items.shop_id', $shopIds)
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->whereIn('orders.payment_status', ['paid', 'commission_paid', 'partial', 'escrow_held', 'verified'])
            ->selectRaw('order_items.shop_id, COALESCE(SUM(order_items.subtotal), 0) as sales_30d')
            ->groupBy('order_items.shop_id')
            ->get()
            ->keyBy('shop_id');

        $result = [];
        foreach ($shopIds as $id) {
            $result[$id] = [
                'orders_count' => (int) ($orders->get($id)?->orders_count ?? 0),
                'sales_30d' => (float) ($sales->get($id)?->sales_30d ?? 0),
            ];
        }
        return $result;
    }

    private function serializeShop(Request $request, Shop $shop, int $productsCount, int $ordersCount, float $sales30d): array
    {
        [$status, $label] = $this->mobileStatus($shop);
        $displayName = Schema::hasColumn('shops', 'display_name')
            ? trim((string) ($shop->getAttribute('display_name') ?? ''))
            : '';
        $subcategory = Schema::hasColumn('shops', 'main_subcategory')
            ? trim((string) ($shop->getAttribute('main_subcategory') ?? ''))
            : '';

        return [
            'id' => $shop->id,
            'name' => (string) $shop->name,
            'display_name' => $displayName !== '' ? $displayName : (string) $shop->name,
            'owner_name' => $this->userName($shop->user),
            'category' => (string) ($shop->main_category ?? ''),
            'subcategory' => $subcategory,
            'city' => (string) ($shop->city ?? ''),
            'commune' => (string) ($shop->commune ?? ''),
            'shop_status' => $status,
            'shop_status_label' => $label,
            'products_count' => $productsCount,
            'orders_count' => $ordersCount,
            'sales_30d' => round($sales30d, 2),
            'logo_url' => $this->publicFileUrl($request, $shop->logo),
            'hero_url' => $this->shopHeroUrl($request, $shop),
        ];
    }

    private function mobileStatus(Shop $shop): array
    {
        if (! $shop->is_active || $shop->status === Shop::STATUS_REJECTED || $shop->logistics_status === Shop::LOGISTICS_SUSPENDED) {
            return ['suspended', 'Suspendue'];
        }

        if ($shop->status === Shop::STATUS_APPROVED && $shop->logistics_status !== Shop::LOGISTICS_INCOMPLETE) {
            return ['online', 'En ligne'];
        }

        if ($shop->status === Shop::STATUS_APPROVED && $shop->usesSellerLogistics() && $shop->logistics_status === Shop::LOGISTICS_INCOMPLETE) {
            return ['pending', 'En attente'];
        }

        return ['pending', 'En attente'];
    }

    private function profile(Request $request, User $user): array
    {
        $name = trim((string) ($user->name ?: (($user->first_name ?? '').' '.($user->last_name ?? ''))));
        return [
            'id' => $user->id,
            'first_name' => (string) ($user->first_name ?: Str::before($name, ' ')),
            'name' => $name,
            'avatar_url' => $this->publicFileUrl($request, $user->avatar),
        ];
    }

    private function publicFileUrl(Request $request, ?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }
        return rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.ltrim($path, '/');
    }

    private function productImageUrl(Request $request, Product $product): ?string
    {
        $url = trim((string) ($product->card_image_url ?? $product->image_url ?? ''));
        if ($url === '') return null;
        if (Str::startsWith($url, ['http://', 'https://'])) return $url;
        if (Str::startsWith($url, ['/storage/', 'storage/'])) {
            return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($url, '/');
        }
        return rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.ltrim($url, '/');
    }

    private function shopHeroUrl(Request $request, Shop $shop): ?string
    {
        $product = Product::query()->where('shop_id', $shop->id)->with('images')->latest('id')->first();
        return $product ? $this->productImageUrl($request, $product) : $this->publicFileUrl($request, $shop->logo);
    }

    private function userName(?User $user): string
    {
        if (! $user) return '';
        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') return $name;
        return trim((string) (($user->first_name ?? '').' '.($user->last_name ?? '')));
    }

    private function payoutMethodLabel(string $method): string
    {
        return match ($method) {
            'orange' => 'Orange Money',
            'mtn' => 'MTN MoMo',
            'moov' => 'Moov Money',
            'wave' => 'Wave',
            'bank' => 'Virement bancaire',
            default => $method,
        };
    }

    private function normalizePhone(string $country, string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $countryDigits = preg_replace('/\D+/', '', $country) ?? '';
        if ($countryDigits !== '' && Str::startsWith($digits, $countryDigits)) {
            return '+'.$digits;
        }
        return '+'.$countryDigits.ltrim($digits, '0');
    }

    /** @param array<int, string> $values */
    private function withDefault(array $values, string $default): array
    {
        $values = array_values(array_filter(array_map(fn ($value) => trim((string) $value), $values)));
        if (! in_array($default, $values, true)) array_unshift($values, $default);
        return array_values(array_unique($values));
    }
}
