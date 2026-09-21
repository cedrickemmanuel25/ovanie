<?php

namespace App\Http\Controllers;

use App\Models\AbidjanCommune;
use App\Models\AbidjanLandmark;
use App\Models\AbidjanQuarter;
use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use App\Services\Geo\AbidjanLocalityRegistry;
use App\Services\Geo\ShopLocationResolver;
use App\Services\GuestCartService;
use App\Services\SellerLogisticsValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['openShop', 'store']);
    }

    public function openShop(AbidjanLocalityRegistry $localities)
    {
        $user = auth()->user();

        if ($user && $user->shop) {
            return redirect()
                ->route('vendor.dashboard')
                ->with('success', 'Votre boutique est déjà ouverte.');
        }

        if ($user && $user->role !== 'vendor') {
            return redirect()
                ->route('client.dashboard')
                ->with('warning', 'Votre identifiant actuel est un compte client. Pour vendre, utilisez un identifiant vendeur distinct.');
        }

        $categories = $this->shopCategories();
        $communes = $localities->communes();
        $initialQuarters = $localities->localitiesForCommune(old('commune'), null, 500);

        return view('open-shop', compact('user', 'categories', 'communes', 'initialQuarters'));
    }

    public function create(AbidjanLocalityRegistry $localities)
    {
        return $this->openShop($localities);
    }

    public function store(Request $request, ShopLocationResolver $locationResolver)
    {
        $user = auth()->user();

        if ($user && $user->shop) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Vous avez déjà une boutique.'], 409)
                : redirect()->route('vendor.dashboard')->with('success', 'Votre boutique est déjà ouverte.');
        }

        if ($user && $user->role !== 'vendor') {
            $message = 'Ce compte est un compte client. La création d’une boutique nécessite un identifiant vendeur distinct.';

            return $request->wantsJson()
                ? response()->json(['error' => $message], 409)
                : redirect()->route('client.dashboard')->with('warning', $message);
        }

        $this->normalizeRequest($request);
        $this->synchronizeStructuredLocation($request);
        $this->enrichLocationFromGps($request, $locationResolver);

        $validated = $this->validateShopRequest($request);
        $geo = $this->resolveShopGeo($request, $locationResolver);

        $identityType = Str::lower((string) $validated['identityType']);

        $shop = DB::transaction(function () use ($request, $validated, &$user, $identityType, $geo) {
            if (! $user) {
                $user = $this->createVendorUser($validated, (string) $request->input('password'));
            } else {
                $this->updateUserSellerInfo($user, $validated);
            }

            $shop = new Shop();
            $this->fillShop($shop, $validated, $identityType, $geo, $user->id);

            $shop->status = Shop::STATUS_APPROVED;
            $shop->is_active = true;
            $shop->approved_at = now();
            $shop->rejection_reason = null;
            $shop->reviewed_by = null;
            $shop->kyc_status = Shop::KYC_PENDING;
            $shop->logistics_status = $validated['logistics_type'] === 'seller'
                ? Shop::LOGISTICS_INCOMPLETE
                : ($this->hasUsableOvanieLocation($geo)
                    ? Shop::LOGISTICS_READY
                    : Shop::LOGISTICS_INCOMPLETE);
            $shop->payout_onboarding_started_at = now();

            $this->storeUploadedDocuments($request, $shop);
            $shop->save();
            $this->syncShopCategories($shop, $validated['categories'] ?? []);

            return $shop;
        });

        if (! auth()->check() && $user && ! $request->attributes->get('ovanie_mobile_skip_web_login', false)) {
            auth()->guard('web')->login($user);
            if ($request->hasSession()) {
                $request->session()->regenerate();
                app(GuestCartService::class)->mergeIntoUserCart($request, $user);
            }
        }

        $shop = app(SellerLogisticsValidator::class)
            ->synchronizeShopStatus($shop)
            ->fresh();

        $successMessage = match (true) {
            $shop->usesSellerLogistics() => 'Votre boutique est ouverte. Configurez maintenant vos zones et tarifs de livraison avant de publier vos produits.',
            $shop->logistics_status !== Shop::LOGISTICS_READY => 'Votre boutique est ouverte. La position exacte doit encore être confirmée depuis votre profil boutique avant la publication des produits.',
            default => 'Votre boutique est ouverte. Vous pouvez maintenant ajouter vos produits.',
        };

        $defaultRedirect = route('vendor.dashboard');
        $intendedRedirect = $request->hasSession()
            ? $request->session()->get('url.intended', $defaultRedirect)
            : $defaultRedirect;

        return $request->wantsJson()
            ? response()->json([
                'has_shop' => true,
                'message' => $successMessage,
                'redirect_url' => $intendedRedirect,
                'shop' => $shop,
            ], 201)
            : redirect()
                ->intended($defaultRedirect)
                ->with('success', $successMessage);
    }

    public function success()
    {
        $user = auth()->user();
        $shop = $user?->shop;

        if (! $shop) {
            return redirect()->route('open-shop');
        }

        return redirect()
            ->route('vendor.dashboard')
            ->with('success', 'Votre boutique est ouverte.');
    }

    public function show(Shop $shop)
    {
        if (View::exists('open-shops.show')) {
            return view('open-shops.show', compact('shop'));
        }

        if (View::exists('shops.show')) {
            return view('shops.show', compact('shop'));
        }

        abort(404, 'La vue de détail boutique est introuvable.');
    }

    public function edit(Shop $shop)
    {
        $this->authorizeShopAccess($shop);

        if (View::exists('shops.edit')) {
            return view('shops.edit', [
                'shop' => $shop,
                'categories' => $this->shopCategories(),
            ]);
        }

        return redirect()
            ->route('vendor.dashboard')
            ->with('info', 'La page de modification boutique n’est pas encore disponible.');
    }

    public function update(Request $request, Shop $shop, ShopLocationResolver $locationResolver)
    {
        $this->authorizeShopAccess($shop);
        $this->normalizeRequest($request);
        // Mode changes go through the dedicated confirmed and validated flow.
        $request->merge(['logistics_type' => $shop->logistics_type ?: 'ovanie']);
        $this->synchronizeStructuredLocation($request);
        $this->preserveExistingExactLocationWhenUnchanged($request, $shop);
        $this->enrichLocationFromGps($request, $locationResolver);

        $validated = $this->validateShopRequest($request, $shop);
        $geo = $this->resolveShopGeo($request, $locationResolver);

        $identityType = Str::lower((string) $validated['identityType']);

        DB::transaction(function () use ($request, $validated, $shop, $identityType, $geo) {
            $oldLogisticsType = $shop->logistics_type;
            $hadKycChange = $request->hasAny([
                'identityType', 'identityNumber', 'identityFile', 'identityFileFront', 'identityFileBack',
            ]);

            $this->fillShop($shop, $validated, $identityType, $geo, $shop->user_id);
            $this->replaceUploadedDocuments($request, $shop);

            if ($hadKycChange) {
                $shop->kyc_status = Shop::KYC_PENDING;
            }

            if ($oldLogisticsType !== $shop->logistics_type) {
                $shop->logistics_status = $shop->usesSellerLogistics()
                    ? Shop::LOGISTICS_INCOMPLETE
                    : Shop::LOGISTICS_READY;
            }

            // Une modification de profil ne désactive jamais automatiquement la boutique.
            $shop->status = Shop::STATUS_APPROVED;
            $shop->is_active = true;
            $shop->approved_at = $shop->approved_at ?: now();
            $shop->save();
            $this->syncShopCategories($shop, $validated['categories'] ?? []);
        });

        app(SellerLogisticsValidator::class)->synchronizeShopStatus($shop->refresh());

        return redirect()
            ->route('vendor.dashboard')
            ->with('success', 'Votre boutique a été mise à jour avec succès.');
    }

    public function myShop(Request $request)
    {
        $shop = Shop::where('user_id', $request->user()->id)->first();

        if (! $shop) {
            return response()->json(['has_shop' => false]);
        }

        return response()->json([
            'has_shop' => true,
            'shop' => $shop,
        ]);
    }

    private function normalizeRequest(Request $request): void
    {
        $commune = trim((string) $request->input('commune'));
        $district = trim((string) $request->input('district'));
        $landmark = trim((string) $request->input('landmark'));
        $address = trim((string) $request->input('address'));

        // Le formulaire est actuellement limité à Abidjan. Ces valeurs sont
        // imposées côté serveur, même si un navigateur modifie le HTML.
        $region = 'Abidjan';
        $city = 'Abidjan';

        // Le formulaire visible reste volontairement simple. L'adresse complète
        // utilisée pour le géocodage est reconstruite à partir des cinq champs.
        if ($address === '') {
            $address = collect([$landmark, $district, $commune, $city])
                ->filter(fn ($value) => filled($value))
                ->implode(', ');
        }

        $request->merge([
            'sellerName' => trim((string) $request->input('sellerName')),
            'sellerEmail' => Str::lower(trim((string) $request->input('sellerEmail'))),
            'sellerPhone' => $this->normalizePhone($request->input('sellerPhone')),
            'shopName' => trim((string) $request->input('shopName')),
            'description' => trim((string) $request->input('description')),
            'companyName' => trim((string) $request->input('companyName')),
            'legalForm' => trim((string) $request->input('legalForm')),
            'rccm' => Str::upper(trim((string) $request->input('rccm'))),
            'taxpayerNumber' => Str::upper(trim((string) $request->input('taxpayerNumber'))),
            'identityCountry' => 'ci',
            'identityType' => Str::lower(trim((string) $request->input('identityType'))),
            'identityNumber' => Str::upper(trim((string) $request->input('identityNumber'))),
            'mmNumber' => $this->normalizePhone($request->input('mmNumber')),
            'whatsapp' => $this->normalizePhone($request->input('whatsapp')),
            'region' => $region,
            'city' => $city,
            'commune' => $commune,
            'district' => $district,
            'landmark' => $landmark,
            'address' => $address,
            'commune_id' => $request->filled('commune_id') ? (int) $request->input('commune_id') : null,
            'quarter_id' => $request->filled('quarter_id') ? (int) $request->input('quarter_id') : null,
            'landmark_id' => $request->filled('landmark_id') ? (int) $request->input('landmark_id') : null,
            'landmark_source' => $request->filled('landmark_source') ? Str::lower(trim((string) $request->input('landmark_source'))) : null,
            'landmark_latitude' => $request->filled('landmark_latitude') ? str_replace(',', '.', (string) $request->input('landmark_latitude')) : null,
            'landmark_longitude' => $request->filled('landmark_longitude') ? str_replace(',', '.', (string) $request->input('landmark_longitude')) : null,
            'latitude' => $request->filled('latitude') ? str_replace(',', '.', (string) $request->input('latitude')) : null,
            'longitude' => $request->filled('longitude') ? str_replace(',', '.', (string) $request->input('longitude')) : null,
            'geo_accuracy' => $request->filled('geo_accuracy') ? str_replace(',', '.', (string) $request->input('geo_accuracy')) : null,
            'geo_source' => $request->filled('geo_source') ? trim((string) $request->input('geo_source')) : null,
            'geo_precision' => $request->filled('geo_precision') ? Str::lower(trim((string) $request->input('geo_precision'))) : null,
            'geo_precision_score' => $request->filled('geo_precision_score') ? (int) $request->input('geo_precision_score') : null,
            'logistics_type' => in_array($request->input('logistics_type'), ['ovanie', 'seller'], true)
                ? $request->input('logistics_type')
                : 'ovanie',
            'payment_mode' => Str::lower(trim((string) $request->input('payment_mode'))),
        ]);
    }

    private function synchronizeStructuredLocation(Request $request): void
    {
        if (! Schema::hasTable('abidjan_communes') || ! Schema::hasTable('abidjan_quarters')) {
            return;
        }

        $commune = null;
        if ($request->filled('commune_id')) {
            $commune = AbidjanCommune::query()
                ->whereKey((int) $request->input('commune_id'))
                ->where('is_active', true)
                ->first();
        }

        if (! $commune && $request->filled('commune')) {
            $commune = AbidjanCommune::query()
                ->where('is_active', true)
                ->where(function ($builder) use ($request) {
                    $value = trim((string) $request->input('commune'));
                    $builder->where('name', $value)->orWhere('slug', Str::slug($value));
                })
                ->first();
        }

        if (! $commune) {
            return;
        }

        $quarter = null;
        if ($request->filled('quarter_id')) {
            $quarter = AbidjanQuarter::query()
                ->whereKey((int) $request->input('quarter_id'))
                ->where('commune_id', $commune->id)
                ->where('is_active', true)
                ->first();
        }

        if (! $quarter && $request->filled('district')) {
            $quarter = AbidjanQuarter::query()
                ->where('commune_id', $commune->id)
                ->where('is_active', true)
                ->where(function ($builder) use ($request) {
                    $value = trim((string) $request->input('district'));
                    $builder->where('name', $value)->orWhere('slug', Str::slug($value));
                })
                ->first();
        }

        $merge = [
            'region' => 'Abidjan',
            'city' => 'Abidjan',
            'commune' => $commune->name,
            'commune_id' => $commune->id,
        ];

        if ($quarter) {
            $merge['district'] = $quarter->name;
            $merge['quarter_id'] = $quarter->id;
        }

        $landmark = null;
        if ($quarter && Schema::hasTable('abidjan_landmarks') && $request->filled('landmark_id')) {
            $landmark = AbidjanLandmark::query()
                ->whereKey((int) $request->input('landmark_id'))
                ->where('commune_id', $commune->id)
                ->where('quarter_id', $quarter->id)
                ->where('is_active', true)
                ->first();
        }

        if ($landmark) {
            $merge['landmark'] = $landmark->name;
            $merge['landmark_id'] = $landmark->id;

            $source = Str::lower((string) $request->input('geo_source'));
            if (! in_array($source, ['browser_gps', 'device_gps', 'manual_map', 'logistics_verified'], true)) {
                $merge['latitude'] = (string) $landmark->latitude;
                $merge['longitude'] = (string) $landmark->longitude;
                $merge['geo_source'] = 'landmark_selection';
                $merge['geo_precision'] = 'low';
                $merge['geo_precision_score'] = 45;
            }
        } elseif ($quarter
            && Schema::hasTable('abidjan_landmarks')
            && $request->filled('landmark')
            && $request->filled('landmark_latitude')
            && $request->filled('landmark_longitude')) {
            $lat = (float) str_replace(',', '.', (string) $request->input('landmark_latitude'));
            $lng = (float) str_replace(',', '.', (string) $request->input('landmark_longitude'));

            if ($this->coordinatesInsideConfiguredCountry($lat, $lng)) {
                $created = AbidjanLandmark::query()->updateOrCreate(
                    [
                        'quarter_id' => $quarter->id,
                        'slug' => Str::slug((string) $request->input('landmark')),
                        'source' => Str::lower((string) ($request->input('landmark_source') ?: 'geocoding')),
                    ],
                    [
                        'commune_id' => $commune->id,
                        'name' => trim((string) $request->input('landmark')),
                        'category' => 'point_de_repere',
                        'address' => trim((string) $request->input('address')) ?: null,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'is_verified' => false,
                        'is_active' => true,
                    ]
                );
                $merge['landmark_id'] = $created->id;
            }
        }

        $request->merge($merge);
    }

    private function preserveExistingExactLocationWhenUnchanged(Request $request, Shop $shop): void
    {
        $source = Str::lower((string) $shop->geo_source);
        if (! in_array($source, ['browser_gps', 'device_gps', 'manual_map', 'logistics_verified'], true)
            || ! is_numeric($shop->latitude)
            || ! is_numeric($shop->longitude)) {
            return;
        }

        $normalize = static fn ($value) => Str::lower(Str::squish((string) $value));
        $locationChanged = collect(['region', 'city', 'commune', 'district', 'landmark', 'address'])
            ->contains(fn (string $field) => $normalize($request->input($field)) !== $normalize($shop->{$field}));

        if ($locationChanged) {
            return;
        }

        if (! $request->filled('geo_source')
            || ! $request->filled('latitude')
            || ! $request->filled('longitude')) {
            $request->merge([
                'latitude' => (string) $shop->latitude,
                'longitude' => (string) $shop->longitude,
                'geo_accuracy' => $shop->geo_accuracy,
                'geo_source' => $source,
                'geo_precision' => $shop->geo_precision ?: 'confirmed',
                'geo_precision_score' => $shop->geo_precision_score ?: 100,
            ]);
        }
    }

    private function enrichLocationFromGps(Request $request, ShopLocationResolver $resolver): void
    {
        if (! $request->filled('latitude') || ! $request->filled('longitude')) {
            return;
        }

        $result = $resolver->reverse(
            (float) $request->input('latitude'),
            (float) $request->input('longitude')
        );

        if (! $result) {
            return;
        }

        $merge = [];
        foreach (['region', 'city', 'commune', 'district', 'landmark', 'address'] as $field) {
            if (! $request->filled($field) && filled($result[$field] ?? null)) {
                $merge[$field] = $result[$field];
            }
        }

        $merge['geo_source'] = $request->input('geo_source') ?: ($result['source'] ?? 'reverse_geocoding');
        $request->merge($merge);
    }

    private function validateShopRequest(Request $request, ?Shop $shop = null): array
    {
        $identityType = Str::lower((string) $request->input('identityType'));
        $identityRegex = [
            'cni' => '/^[A-Z0-9-]{6,25}$/',
            'passport' => '/^[A-Z][A-Z0-9]{6,11}$/',
            'permis' => '/^[A-Z0-9-]{6,25}$/',
            'resident' => '/^[A-Z0-9-]{6,25}$/',
        ];

        $shopId = $shop?->id;
        $isUpdate = $shop !== null;
        $userId = auth()->id();
        $hasStructuredLocation = Schema::hasTable('abidjan_communes')
            && Schema::hasTable('abidjan_quarters');

        $communeIdRules = $hasStructuredLocation
            ? ['required', 'integer', Rule::exists('abidjan_communes', 'id')->where(fn ($query) => $query->where('is_active', true))]
            : ['nullable', 'integer'];
        $quarterIdRules = $hasStructuredLocation
            ? ['required', 'integer', Rule::exists('abidjan_quarters', 'id')->where(fn ($query) => $query
                ->where('commune_id', (int) $request->input('commune_id'))
                ->where('is_active', true))]
            : ['nullable', 'integer'];
        $landmarkIdRules = $hasStructuredLocation && Schema::hasTable('abidjan_landmarks')
            ? ['nullable', 'integer', Rule::exists('abidjan_landmarks', 'id')->where(fn ($query) => $query
                ->where('commune_id', (int) $request->input('commune_id'))
                ->where('quarter_id', (int) $request->input('quarter_id'))
                ->where('is_active', true))]
            : ['nullable', 'integer'];

        $rules = [
            'sellerName' => ['required', 'string', 'max:255'],
            'sellerEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'sellerPhone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($userId)],
            'sellerType' => ['required', Rule::in(['particulier', 'entreprise', 'artisan', 'grossiste'])],

            'companyName' => ['required_if:sellerType,entreprise', 'nullable', 'string', 'max:255'],
            'legalForm' => ['required_if:sellerType,entreprise', 'nullable', Rule::in(['sarl', 'sarlu', 'sa', 'sas', 'ei', 'cooperative', 'autre'])],
            'rccm' => [
                'required_if:sellerType,entreprise',
                'nullable',
                'string',
                'max:100',
                Rule::unique('shops', 'rccm')->ignore($shopId),
            ],
            'taxpayerNumber' => ['required_if:sellerType,entreprise', 'nullable', 'string', 'max:100'],
            'rccmFile' => [$this->companyFileRule($request, $shop, 'rccm_file'), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'taxFile' => [$this->companyFileRule($request, $shop, 'tax_file'), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],

            'shopName' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:30', 'max:700'],
            'selfie' => [$isUpdate && $shop->selfie ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            // Photo/logo public utilisé dans l'application vendeur. Le selfie reste
            // conservé séparément sur le disque privé pour le KYC.
            'shopLogo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'region' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'commune' => ['required', 'string', 'max:120'],
            'commune_id' => $communeIdRules,
            'district' => ['required', 'string', 'max:150'],
            'quarter_id' => $quarterIdRules,
            'landmark' => ['required', 'string', 'max:255'],
            'landmark_id' => $landmarkIdRules,
            'landmark_source' => ['nullable', 'string', 'max:50'],
            'landmark_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'landmark_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => [Rule::requiredIf(fn () => $request->input('logistics_type') === 'ovanie'), 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [Rule::requiredIf(fn () => $request->input('logistics_type') === 'ovanie'), 'nullable', 'numeric', 'between:-180,180'],
            'geo_accuracy' => ['nullable', 'numeric', 'min:0'],
            'geo_source' => [
                Rule::requiredIf(fn () => $request->input('logistics_type') === 'ovanie'),
                'nullable',
                'string',
                'max:80',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if ($request->input('logistics_type') !== 'ovanie') {
                        return;
                    }

                    $source = Str::lower(trim((string) $value));
                    if (! in_array($source, ['browser_gps', 'device_gps', 'manual_map', 'logistics_verified'], true)) {
                        $fail('La position GPS exacte de la boutique doit être capturée pour OVANIE Logistics.');
                    }
                },
            ],
            'geo_precision' => ['nullable', Rule::in(['high', 'medium', 'low', 'confirmed'])],
            'geo_precision_score' => ['nullable', 'integer', 'between:0,100'],

            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => $this->categoryValidationRules(),
            'delivery_zone' => ['required', Rule::in(['abidjan', 'grand_abidjan', 'national', 'afrique_ouest'])],
            'logistics_type' => ['required', Rule::in(['ovanie', 'seller'])],

            'whatsapp' => ['nullable', 'string', 'max:30'],
            'business_email' => ['nullable', 'email', 'max:255'],

            'identityCountry' => ['required', Rule::in(['ci'])],
            'identityType' => ['required', Rule::in(['cni', 'passport', 'permis', 'resident'])],
            'identityNumber' => [
                'required',
                'string',
                'max:25',
                'regex:' . ($identityRegex[$identityType] ?? '/^[A-Z0-9-]{6,25}$/'),
                Rule::unique('shops', 'identity_number')->ignore($shopId),
            ],
            'identityUploadMode' => ['required', Rule::in(['pdf', 'scan'])],
            'identityFile' => [$this->identityFileRule($request, $shop, 'pdf'), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'identityFileFront' => [$this->identityFileRule($request, $shop, 'scan_front'), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'identityFileBack' => [$this->identityFileRule($request, $shop, 'scan_back'), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'direct_payment' => ['nullable', 'boolean'],
            'payment_mode' => ['required', Rule::in(['post_delivery', 'weekly'])],
            'mmOperator' => ['required', Rule::in(['orange', 'mtn', 'wave', 'moov'])],
            'mmNumber' => ['required', 'regex:/^\+?[0-9]{8,15}$/'],
            'mmHolder' => ['required', 'string', 'max:255'],
            'terms' => ['accepted'],
        ];

        if (! auth()->check()) {
            $rules['password'] = ['required', 'string', 'confirmed', 'min:8'];
        }

        $messages = [
            'sellerName.required' => 'Le nom complet est obligatoire.',
            'sellerEmail.required' => 'L’adresse email est obligatoire.',
            'sellerEmail.unique' => 'Cette adresse email est déjà utilisée.',
            'sellerPhone.required' => 'Le téléphone est obligatoire.',
            'sellerPhone.unique' => 'Ce téléphone est déjà utilisé. Connectez-vous au compte associé ou utilisez un autre numéro.',
            'companyName.required_if' => 'La raison sociale est obligatoire pour une entreprise.',
            'legalForm.required_if' => 'La forme juridique est obligatoire pour une entreprise.',
            'rccm.required_if' => 'Le numéro RCCM est obligatoire pour une entreprise.',
            'rccm.unique' => 'Ce numéro RCCM est déjà associé à une boutique.',
            'taxpayerNumber.required_if' => 'Le numéro contribuable est obligatoire pour une entreprise.',
            'rccmFile.required' => 'Le document RCCM est obligatoire.',
            'taxFile.required' => 'Le document fiscal est obligatoire.',
            'shopName.required' => 'Le nom de la boutique est obligatoire.',
            'description.min' => 'Présentez votre activité en au moins 30 caractères.',
            'selfie.required' => 'Une photo récente du responsable est obligatoire.',
            'region.required' => 'La région est obligatoire.',
            'city.required' => 'La ville est obligatoire.',
            'commune.required' => 'La commune est obligatoire.',
            'commune_id.required' => 'Sélectionnez une commune dans la liste proposée.',
            'commune_id.exists' => 'La commune sélectionnée n’est plus disponible. Choisissez-la de nouveau.',
            'district.required' => 'Le quartier est obligatoire.',
            'quarter_id.required' => 'Sélectionnez un quartier correspondant à la commune.',
            'quarter_id.exists' => 'Le quartier sélectionné ne correspond pas à cette commune.',
            'landmark.required' => 'Le point de repère est obligatoire.',
            'landmark_id.exists' => 'Le point de repère sélectionné ne correspond pas à ce quartier.',
            'address.required' => 'L’adresse de la boutique est obligatoire.',
            'latitude.required' => 'Autorisez la localisation afin qu’OVANIE enregistre la position GPS exacte de la boutique.',
            'longitude.required' => 'Autorisez la localisation afin qu’OVANIE enregistre la position GPS exacte de la boutique.',
            'geo_source.required' => 'La position GPS exacte de la boutique est obligatoire avec OVANIE Logistics.',
            'categories.required' => 'Choisissez au moins une catégorie.',
            'categories.min' => 'Choisissez au moins une catégorie.',
            'categories.*.exists' => 'Une des catégories sélectionnées n’est plus disponible.',
            'delivery_zone.required' => 'Choisissez une zone commerciale.',
            'logistics_type.required' => 'Choisissez le mode logistique de la boutique.',
            'identityNumber.regex' => 'Le format du numéro de pièce est invalide.',
            'identityNumber.unique' => 'Ce numéro de pièce est déjà utilisé.',
            'identityFile.required_if' => 'Le fichier de la pièce d’identité est obligatoire.',
            'identityFileFront.required_if' => 'L’image recto est obligatoire.',
            'identityFileBack.required_if' => 'L’image verso est obligatoire.',
            'payment_mode.required' => 'Choisissez un calendrier de reversement.',
            'mmOperator.required' => 'Choisissez l’opérateur Mobile Money.',
            'mmNumber.required' => 'Le numéro de réception est obligatoire.',
            'mmNumber.regex' => 'Saisissez un numéro Mobile Money valide.',
            'mmHolder.required' => 'Le nom du titulaire du compte est obligatoire.',
            'terms.accepted' => 'Vous devez accepter les conditions vendeur.',
            'password.required' => 'Le mot de passe est obligatoire pour créer le compte vendeur.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ];

        return $request->validate($rules, $messages);
    }

    private function categoryValidationRules(): array
    {
        if (Schema::hasTable('categories') && Category::query()->exists()) {
            $rule = Rule::exists('categories', 'slug');

            if (Schema::hasColumn('categories', 'status')) {
                $rule->where(fn ($query) => $query->where('status', 'actif'));
            }

            if (Schema::hasColumn('categories', 'is_active')) {
                $rule->where(fn ($query) => $query->where('is_active', true));
            }

            if (Schema::hasColumn('categories', 'parent_id')) {
                $rule->where(fn ($query) => $query->whereNull('parent_id'));
            }

            return ['required', 'string', $rule];
        }

        return ['required', Rule::in(array_keys($this->fallbackCategories()))];
    }

    private function identityFileRule(Request $request, ?Shop $shop, string $type): string
    {
        $mode = $request->input('identityUploadMode');

        if (! $shop) {
            return match ($type) {
                'pdf' => 'required_if:identityUploadMode,pdf',
                'scan_front', 'scan_back' => 'required_if:identityUploadMode,scan',
                default => 'nullable',
            };
        }

        if ($mode === 'pdf' && $type === 'pdf' && ! $shop->identity_file) {
            return 'required';
        }

        if ($mode === 'scan' && $type === 'scan_front' && ! $shop->identity_file_front) {
            return 'required';
        }

        if ($mode === 'scan' && $type === 'scan_back' && ! $shop->identity_file_back) {
            return 'required';
        }

        return 'nullable';
    }

    private function companyFileRule(Request $request, ?Shop $shop, string $column): string
    {
        if ($request->input('sellerType') !== 'entreprise') {
            return 'nullable';
        }

        if (! $shop || ! filled($shop->{$column})) {
            return 'required';
        }

        return 'nullable';
    }

    private function resolveShopGeo(Request $request, ShopLocationResolver $resolver): array|false|null
    {
        if ($request->filled('latitude') && $request->filled('longitude')) {
            $source = Str::lower((string) ($request->input('geo_source') ?: 'address_geocoding'));
            $latitude = (float) $request->input('latitude');
            $longitude = (float) $request->input('longitude');

            if (! $this->coordinatesInsideConfiguredCountry($latitude, $longitude)) {
                return $request->input('logistics_type') === 'seller' ? null : false;
            }

            // GPS et carte sont des confirmations explicites. Un repère sélectionné
            // fournit une vraie coordonnée de référence, mais reste à confirmer car
            // la boutique peut se situer à quelques mètres du repère.
            if (in_array($source, ['browser_gps', 'device_gps', 'manual_map', 'logistics_verified', 'landmark_selection'], true)) {
                $precision = $source === 'landmark_selection'
                    ? 'low'
                    : (string) ($request->input('geo_precision') ?: $this->precisionFromSource($source, $request->input('geo_accuracy')));

                return [
                    'latitude' => round($latitude, 7),
                    'longitude' => round($longitude, 7),
                    'source' => $source,
                    'precision' => $precision,
                    'precision_score' => $source === 'landmark_selection'
                        ? 45
                        : ($request->filled('geo_precision_score')
                            ? (int) $request->input('geo_precision_score')
                            : $this->defaultPrecisionScore($precision)),
                    'status' => $this->geoStatusFor($source, $precision),
                ];
            }
        }

        $result = $resolver->search([
            'address' => $request->input('address'),
            'region' => $request->input('region'),
            'commune' => $request->input('commune'),
            'district' => $request->input('district'),
            'landmark' => $request->input('landmark'),
            'city' => $request->input('city'),
            'country' => "Côte d'Ivoire",
        ]);

        if (! $result) {
            return $request->input('logistics_type') === 'seller' ? null : false;
        }

        $source = (string) ($result['source'] ?? 'geocoding');
        $precision = (string) ($result['precision'] ?? 'low');

        return [
            'latitude' => round((float) $result['latitude'], 7),
            'longitude' => round((float) $result['longitude'], 7),
            'source' => $source,
            'precision' => $precision,
            'precision_score' => (int) ($result['precision_score'] ?? $this->defaultPrecisionScore($precision)),
            'status' => $this->geoStatusFor($source, $precision),
        ];
    }

    private function fillShop(Shop $shop, array $validated, string $identityType, array|false|null $geo, ?int $userId = null): void
    {
        $shop->user_id = $userId ?: $shop->user_id ?: auth()->id();

        $shop->name = $validated['shopName'];
        $shop->slug = $this->generateUniqueSlug($validated['shopName'], $shop->exists ? $shop->id : null);
        $shop->seller_type = $validated['sellerType'];
        $shop->description = $validated['description'];

        $shop->company_name = $validated['sellerType'] === 'entreprise' ? ($validated['companyName'] ?? null) : null;
        $shop->legal_form = $validated['sellerType'] === 'entreprise' ? ($validated['legalForm'] ?? null) : null;
        $shop->rccm = $validated['sellerType'] === 'entreprise' ? ($validated['rccm'] ?? null) : null;
        $shop->taxpayer_number = $validated['sellerType'] === 'entreprise' ? ($validated['taxpayerNumber'] ?? null) : null;

        $shop->region = $validated['region'];
        $shop->city = $validated['city'];
        $shop->commune = $validated['commune'];
        $shop->district = $validated['district'];
        $shop->landmark = $validated['landmark'];
        if (Schema::hasColumn('shops', 'commune_id')) {
            $shop->commune_id = $validated['commune_id'] ?? null;
        }
        if (Schema::hasColumn('shops', 'quarter_id')) {
            $shop->quarter_id = $validated['quarter_id'] ?? null;
        }
        if (Schema::hasColumn('shops', 'landmark_id')) {
            $shop->landmark_id = $validated['landmark_id'] ?? null;
        }
        $shop->address = $validated['address'];
        $shop->latitude = is_array($geo) ? $geo['latitude'] : ($validated['latitude'] ?? null);
        $shop->longitude = is_array($geo) ? $geo['longitude'] : ($validated['longitude'] ?? null);
        $shop->geo_accuracy = $validated['geo_accuracy'] ?? null;
        $shop->geo_source = is_array($geo) ? ($geo['source'] ?? null) : ($validated['geo_source'] ?? null);
        $shop->geo_precision = is_array($geo) ? ($geo['precision'] ?? null) : ($validated['geo_precision'] ?? null);
        $shop->geo_precision_score = is_array($geo)
            ? ($geo['precision_score'] ?? null)
            : ($validated['geo_precision_score'] ?? null);
        $shop->geo_status = is_array($geo)
            ? ($geo['status'] ?? Shop::GEO_STATUS_VERIFICATION_REQUIRED)
            : Shop::GEO_STATUS_VERIFICATION_REQUIRED;

        // Une adresse géocodée n'est pas automatiquement une position « vérifiée ».
        // geo_verified_at est réservé au GPS, à une confirmation cartographique ou
        // à une validation interne par l'équipe logistique.
        if (is_array($geo) && ($geo['status'] ?? null) === Shop::GEO_STATUS_VERIFIED) {
            $shop->geo_verified_at = now();
        } else {
            $shop->geo_verified_at = null;
        }

        // main_category reste peuplée (compat lecture) avec la première catégorie
        // choisie ; la liste complète est synchronisée séparément dans la table
        // pivot shop_category une fois la boutique enregistrée (voir syncShopCategories).
        $shop->main_category = $validated['categories'][0] ?? null;
        $shop->delivery_zone = $validated['delivery_zone'];
        $shop->processing_time = $validated['processing_time'] ?? null;
        $shop->logistics_type = $validated['logistics_type'];
        $shop->whatsapp = $validated['whatsapp'] ?? null;
        $shop->business_email = $validated['business_email'] ?? null;

        $shop->identity_country = $validated['identityCountry'];
        $shop->identity_type = $identityType;
        $shop->identity_number = $validated['identityNumber'];
        $shop->identity_upload_mode = $validated['identityUploadMode'];

        // direct_payment est conservé pour compatibilité historique uniquement.
        $shop->direct_payment = false;
        $shop->payment_mode = $validated['payment_mode'];
        $shop->mm_operator = $validated['mmOperator'];
        $shop->mm_number = $validated['mmNumber'];
        $shop->mm_holder = $validated['mmHolder'];
    }

    /**
     * @param string[] $categorySlugs
     */
    private function syncShopCategories(Shop $shop, array $categorySlugs): void
    {
        if ($categorySlugs === [] || ! Schema::hasTable('shop_category') || ! Schema::hasTable('categories')) {
            return;
        }

        $categoryIds = Category::query()
            ->whereIn('slug', $categorySlugs)
            ->pluck('id', 'slug');

        // Préserve l'ordre choisi par le vendeur (categories[0] = catégorie
        // principale) plutôt que l'ordre renvoyé par la requête SQL.
        $orderedIds = collect($categorySlugs)
            ->map(fn (string $slug) => $categoryIds->get($slug))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $shop->categories()->sync($orderedIds);
    }

    private function coordinatesInsideConfiguredCountry(float $latitude, float $longitude): bool
    {
        $bounds = (array) config('geo.country_bounds', []);

        return $latitude >= (float) ($bounds['min_lat'] ?? -90)
            && $latitude <= (float) ($bounds['max_lat'] ?? 90)
            && $longitude >= (float) ($bounds['min_lng'] ?? -180)
            && $longitude <= (float) ($bounds['max_lng'] ?? 180);
    }

    private function hasUsableOvanieLocation(array|false|null $geo): bool
    {
        if (! is_array($geo)) {
            return false;
        }

        return filled($geo['latitude'] ?? null)
            && filled($geo['longitude'] ?? null)
            && ($geo['status'] ?? Shop::GEO_STATUS_VERIFICATION_REQUIRED) !== Shop::GEO_STATUS_VERIFICATION_REQUIRED;
    }

    private function geoStatusFor(string $source, string $precision): string
    {
        $normalizedSource = Str::lower($source);

        if (in_array($normalizedSource, ['browser_gps', 'device_gps', 'manual_map', 'logistics_verified'], true)) {
            return Shop::GEO_STATUS_VERIFIED;
        }

        return match ($precision) {
            'high' => Shop::GEO_STATUS_RELIABLE,
            'medium' => Shop::GEO_STATUS_REVIEW_RECOMMENDED,
            default => Shop::GEO_STATUS_VERIFICATION_REQUIRED,
        };
    }

    private function precisionFromSource(string $source, mixed $accuracy): string
    {
        if (Str::lower($source) === 'manual_map') {
            return 'confirmed';
        }

        if (in_array(Str::lower($source), ['browser_gps', 'device_gps'], true) && is_numeric($accuracy)) {
            $meters = (float) $accuracy;

            if ($meters > 0 && $meters <= 30) {
                return 'high';
            }

            if ($meters > 0 && $meters <= 100) {
                return 'medium';
            }

            return 'low';
        }

        return 'medium';
    }

    private function defaultPrecisionScore(string $precision): int
    {
        return match ($precision) {
            'confirmed' => 100,
            'high' => 90,
            'medium' => 65,
            default => 35,
        };
    }

    private function createVendorUser(array $validated, string $password): User
    {
        $name = trim($validated['sellerName']);
        $parts = preg_split('/\s+/', $name, 2);

        return User::create([
            'first_name' => $parts[0] ?? $name,
            'last_name' => $parts[1] ?? '',
            'name' => $name,
            'email' => $validated['sellerEmail'],
            'phone' => $validated['sellerPhone'],
            'role' => 'vendor',
            'password' => Hash::make($password),
            'status' => 'active',
        ]);
    }

    private function storeUploadedDocuments(Request $request, Shop $shop): void
    {
        if ($request->hasFile('selfie')) {
            $shop->selfie = $request->file('selfie')->store('kyc/selfies', 'local');
        }

        if ($request->hasFile('shopLogo')) {
            $shop->logo = $request->file('shopLogo')->store('shops/logos', 'public');
        }

        if ($request->hasFile('identityFile')) {
            $shop->identity_file = $request->file('identityFile')->store('kyc/identity', 'local');
        }

        if ($request->hasFile('identityFileFront')) {
            $shop->identity_file_front = $request->file('identityFileFront')->store('kyc/identity', 'local');
        }

        if ($request->hasFile('identityFileBack')) {
            $shop->identity_file_back = $request->file('identityFileBack')->store('kyc/identity', 'local');
        }

        if ($request->hasFile('rccmFile')) {
            $shop->rccm_file = $request->file('rccmFile')->store('kyc/company', 'local');
        }

        if ($request->hasFile('taxFile')) {
            $shop->tax_file = $request->file('taxFile')->store('kyc/company', 'local');
        }
    }

    private function replaceUploadedDocuments(Request $request, Shop $shop): void
    {
        if ($request->hasFile('selfie')) {
            $this->deleteLocalFile($shop->selfie);
            $shop->selfie = $request->file('selfie')->store('kyc/selfies', 'local');
        }

        if ($request->hasFile('shopLogo')) {
            if ($shop->logo && Storage::disk('public')->exists($shop->logo)) {
                Storage::disk('public')->delete($shop->logo);
            }
            $shop->logo = $request->file('shopLogo')->store('shops/logos', 'public');
        }

        if ($request->hasFile('identityFile')) {
            $this->deleteLocalFile($shop->identity_file);
            $this->deleteLocalFile($shop->identity_file_front);
            $this->deleteLocalFile($shop->identity_file_back);
            $shop->identity_file = $request->file('identityFile')->store('kyc/identity', 'local');
            $shop->identity_file_front = null;
            $shop->identity_file_back = null;
        }

        if ($request->hasFile('identityFileFront')) {
            $this->deleteLocalFile($shop->identity_file_front);
            $this->deleteLocalFile($shop->identity_file);
            $shop->identity_file_front = $request->file('identityFileFront')->store('kyc/identity', 'local');
            $shop->identity_file = null;
        }

        if ($request->hasFile('identityFileBack')) {
            $this->deleteLocalFile($shop->identity_file_back);
            $this->deleteLocalFile($shop->identity_file);
            $shop->identity_file_back = $request->file('identityFileBack')->store('kyc/identity', 'local');
            $shop->identity_file = null;
        }

        if ($request->hasFile('rccmFile')) {
            $this->deleteLocalFile($shop->rccm_file);
            $shop->rccm_file = $request->file('rccmFile')->store('kyc/company', 'local');
        }

        if ($request->hasFile('taxFile')) {
            $this->deleteLocalFile($shop->tax_file);
            $shop->tax_file = $request->file('taxFile')->store('kyc/company', 'local');
        }
    }

    private function updateUserSellerInfo(User $user, array $validated): void
    {
        $nameParts = preg_split('/\s+/', trim($validated['sellerName']), 2);

        $user->fill([
            'first_name' => $nameParts[0] ?? $user->first_name,
            'last_name' => $nameParts[1] ?? '',
            'name' => $validated['sellerName'],
            'email' => $validated['sellerEmail'],
            'phone' => $validated['sellerPhone'],
            'role' => 'vendor',
        ])->save();
    }

    private function generateUniqueSlug(string $shopName, ?int $ignoreShopId = null): string
    {
        $slugBase = Str::slug($shopName) ?: 'boutique';
        $slug = $slugBase;
        $suffix = 2;

        while (Shop::where('slug', $slug)
            ->when($ignoreShopId, fn ($query) => $query->where('id', '!=', $ignoreShopId))
            ->exists()) {
            $slug = $slugBase . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function shopCategories()
    {
        try {
            if (Schema::hasTable('categories') && Category::query()->exists()) {
                return Category::query()
                    ->whereNull('parent_id')
                    ->when(Schema::hasColumn('categories', 'status'), fn ($query) => $query->where('status', 'actif'))
                    ->when(Schema::hasColumn('categories', 'is_active'), fn ($query) => $query->where('is_active', true))
                    ->orderBy(Schema::hasColumn('categories', 'sort_order') ? 'sort_order' : 'name')
                    ->get(['id', 'name', 'slug']);
            }
        } catch (\Throwable) {
            // Le fallback ci-dessous garde le formulaire utilisable pendant les migrations.
        }

        return collect($this->fallbackCategories())
            ->map(fn (string $name, string $slug) => (object) compact('name', 'slug'))
            ->values();
    }

    private function fallbackCategories(): array
    {
        return [
            'ciment-beton' => 'Ciment & Béton',
            'fer-metaux' => 'Fer & Métaux',
            'carrelage' => 'Carrelage',
            'peinture' => 'Peinture',
            'isolation' => 'Isolation',
            'electricite' => 'Électricité',
            'plomberie' => 'Plomberie',
            'bois' => 'Bois',
            'outillage' => 'Outillage',
            'solaire' => 'Énergie solaire',
            'quincaillerie' => 'Quincaillerie',
            'materiaux-ecologiques' => 'Matériaux écologiques',
        ];
    }

    private function authorizeShopAccess(Shop $shop): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $isOwner = (int) $shop->user_id === (int) $user->id;
        $isAdmin = (bool) ($user->is_admin ?? false);

        abort_unless($isOwner || $isAdmin, 403, 'Accès non autorisé à cette boutique.');
    }

    private function deleteLocalFile(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function normalizePhone(mixed $value): ?string
    {
        $value = preg_replace('/[^0-9+]/', '', trim((string) $value));

        return $value !== '' ? $value : null;
    }
}
