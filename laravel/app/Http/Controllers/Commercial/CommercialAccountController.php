<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CommercialProspect;
use App\Models\Shop;
use App\Models\User;
use App\Services\CommercialShopLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CommercialAccountController extends Controller
{
    public function create(Request $request)
    {
        return redirect()->route(
            $request->query('type') === 'vendor'
                ? 'commercial.vendors.create'
                : 'commercial.clients.create'
        );
    }

    public function createClient()
    {
        return view('commercial.clients.create');
    }

    public function createVendor(Request $request)
    {
        $prospect = $request->filled('prospect_id')
            ? CommercialProspect::with(['commune','quarter','locality'])->find($request->integer('prospect_id'))
            : null;

        return view('commercial.vendors.create', [
            'prospect' => $prospect,
            'prospectPhone' => $prospect ? preg_replace('/^\+?225/', '', (string) $prospect->phone) : '',
            'prospectWhatsapp' => $prospect ? preg_replace('/^\+?225/', '', (string) $prospect->whatsapp) : '',
            'categories' => Schema::hasTable('categories')
                ? Category::query()->orderBy('name')->get(['name', 'slug'])
                : collect(),
            'mapboxToken' => (string) (config('services.mapbox.public_token') ?: config('geo.mapbox.public_token')),
            'mapboxStyle' => (string) (config('services.mapbox.style_url') ?: config('geo.mapbox.style_url') ?: 'mapbox://styles/mapbox/streets-v12'),
        ]);
    }

    public function storeClient(Request $request, CommercialShopLocationService $locations)
    {
        $request->merge(['account_type' => 'client']);

        return $this->store($request, $locations);
    }

    public function storeVendor(Request $request, CommercialShopLocationService $locations)
    {
        $request->merge(['account_type' => 'vendor']);

        return $this->store($request, $locations);
    }

    public function store(Request $request, CommercialShopLocationService $locations)
    {
        if ($request->input('account_type') === 'vendor' && $request->filled('mmNumber')) {
            $request->merge([
                'mmNumber' => preg_replace('/[^0-9+]/', '', (string) $request->input('mmNumber')),
            ]);
        }

        $data = $request->validate($this->rules($request), [
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'identityNumber.regex' => 'Le format du numéro de la pièce est invalide.',
            'mmNumber.regex' => 'Saisissez un numéro Mobile Money valide, par exemple 0701020304.',
            'latitude.required' => 'Capturez la position de la boutique ou choisissez de la confirmer plus tard.',
            'longitude.required' => 'Capturez la position de la boutique ou choisissez de la confirmer plus tard.',
            'location_confirmed.accepted' => 'Confirmez que la position correspond au point d’enlèvement de la boutique.',
        ]);

        $commercial = $request->user();
        if (! empty($data['prospect_id'])) {
            $prospect = CommercialProspect::query()->find((int) $data['prospect_id']);
            if ($prospect?->shop_id) {
                return back()->withInput()->with('error', 'Ce vendeur potentiel est déjà lié à une boutique OVANIE.');
            }
        }
        $phone = $this->normalizePhone($data['phone_country'] ?? '+225', $data['phone']);
        $geoPayload = $data['account_type'] === 'vendor'
            ? $locations->creationPayload($data)
            : [];

        [$user, $shop] = DB::transaction(function () use ($request, $data, $commercial, $phone, $geoPayload) {
            $user = User::create([
                'created_by_commercial_id' => $commercial->id,
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => Str::lower($data['email']),
                'phone' => $phone,
                'password' => Hash::make($data['password']),
                'role' => $data['account_type'] === 'vendor' ? 'vendor' : 'client',
                'status' => 'active',
                'city' => $data['account_type'] === 'vendor' ? $data['city'] : null,
            ]);

            if ($data['account_type'] !== 'vendor') {
                return [$user, null];
            }

            $shop = Shop::create([
                'user_id' => $user->id,
                'created_by_commercial_id' => $commercial->id,
                'managed_by_commercial_id' => $commercial->id,
                'name' => trim($data['shopName']),
                'slug' => Str::slug($data['shopName']).'-'.Str::lower(Str::random(6)),
                'description' => $data['description'] ?? null,
                'seller_type' => $data['sellerType'],
                'company_name' => $data['sellerType'] === 'entreprise' ? $data['companyName'] : null,
                'legal_form' => $data['sellerType'] === 'entreprise' ? $data['legalForm'] : null,
                'rccm' => $data['sellerType'] === 'entreprise' ? $data['rccm'] : null,
                'taxpayer_number' => $data['sellerType'] === 'entreprise' ? $data['taxpayerNumber'] : null,
                'region' => trim($data['region']),
                'city' => trim($data['city']),
                'commune' => trim($data['commune']),
                'district' => trim($data['district']),
                'landmark' => filled($data['landmark'] ?? null) ? trim($data['landmark']) : null,
                'address' => filled($data['address'] ?? null)
                    ? trim($data['address'])
                    : collect([$data['district'], $data['commune'], $data['city']])->filter()->implode(', '),
                'main_category' => $data['main_category'],
                'delivery_zone' => $data['delivery_zone'],
                'logistics_type' => $data['logistics_type'],
                'whatsapp' => $data['whatsapp'] ?? null,
                'business_email' => $data['business_email'] ?? null,
                'identity_country' => $data['identityCountry'],
                'identity_type' => Str::lower($data['identityType']),
                'identity_number' => Str::upper($data['identityNumber']),
                'identity_upload_mode' => $data['identityUploadMode'],
                'payment_mode' => $data['payment_mode'],
                'mm_operator' => $data['mmOperator'],
                'mm_number' => $data['mmNumber'],
                'mm_holder' => $data['mmHolder'],
                'direct_payment' => false,
                'status' => Shop::STATUS_APPROVED,
                'kyc_status' => Shop::KYC_PENDING,
                'is_active' => true,
                'approved_at' => now(),
                ...$geoPayload,
            ]);

            $this->storeDocuments($request, $shop);

            return [$user, $shop];
        });

        if ($shop && ! empty($data['prospect_id'])) {
            CommercialProspect::query()->whereKey((int) $data['prospect_id'])->update([
                'vendor_user_id' => $user->id,
                'shop_id' => $shop->id,
                'status' => 'shop_opened',
                'converted_at' => now(),
                'next_follow_up_at' => null,
            ]);
        }

        if (! $shop) {
            return redirect()
                ->route('commercial.clients.index')
                ->with('success', 'Le compte client a été créé et vous est attribué.');
        }

        $message = match (true) {
            $shop->usesOvanieLogistics() && $shop->logistics_status === Shop::LOGISTICS_READY
                => 'Le vendeur et sa boutique ont été créés. La position du point d’enlèvement est enregistrée pour OVANIE Logistics.',
            $shop->usesOvanieLogistics()
                => 'Le vendeur et sa boutique ont été créés. La position GPS devra être confirmée avant la publication des produits.',
            default
                => 'Le vendeur et sa boutique ont été créés. La configuration de sa logistique doit être complétée avant la publication des produits.',
        };

        return redirect()
            ->route('commercial.vendors.index')
            ->with('success', $message);
    }

    private function rules(Request $request): array
    {
        $vendor = $request->input('account_type') === 'vendor';
        $identityType = Str::lower((string) $request->input('identityType'));
        $identityRegex = [
            'cni' => '/^[A-Z0-9-]{6,25}$/',
            'passport' => '/^[A-Z][A-Z0-9]{6,11}$/',
            'permis' => '/^[A-Z0-9-]{6,25}$/',
            'resident' => '/^[A-Z0-9-]{6,25}$/',
        ];

        $rules = [
            'prospect_id' => ['nullable', 'integer', 'exists:commercial_prospects,id'],
            'account_type' => ['required', Rule::in(['client', 'vendor'])],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_country' => ['required', Rule::in(['+225', '+221', '+223', '+226', '+233', '+224'])],
            'phone' => [
                'required',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $normalized = $this->normalizePhone((string) $request->input('phone_country', '+225'), (string) $value);

                    if (User::query()->where('phone', $normalized)->exists()) {
                        $fail('Ce numéro de téléphone est déjà utilisé.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];

        if (! $vendor) {
            return $rules;
        }

        $ovanieLogistics = $request->input('logistics_type') === 'ovanie';
        $captureNow = $ovanieLogistics && $request->input('location_mode') === 'gps_now';

        return array_merge($rules, [
            'sellerType' => ['required', Rule::in(['particulier', 'entreprise', 'artisan', 'grossiste'])],
            'companyName' => ['required_if:sellerType,entreprise', 'nullable', 'string', 'max:255'],
            'legalForm' => ['required_if:sellerType,entreprise', 'nullable', Rule::in(['sarl', 'sarlu', 'sa', 'sas', 'ei', 'cooperative', 'autre'])],
            'rccm' => ['required_if:sellerType,entreprise', 'nullable', 'string', 'max:100', 'unique:shops,rccm'],
            'taxpayerNumber' => ['required_if:sellerType,entreprise', 'nullable', 'string', 'max:100'],
            'rccmFile' => ['required_if:sellerType,entreprise', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'taxFile' => ['required_if:sellerType,entreprise', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'shopName' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:700'],
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'region' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'commune' => ['required', 'string', 'max:120'],
            'district' => ['required', 'string', 'max:150'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'main_category' => ['required', 'string', 'max:160'],
            'delivery_zone' => ['required', Rule::in(['abidjan', 'grand_abidjan', 'national', 'afrique_ouest'])],
            'logistics_type' => ['required', Rule::in(['ovanie', 'seller'])],
            'location_mode' => [Rule::requiredIf($ovanieLogistics), 'nullable', Rule::in(['gps_now', 'later'])],
            'latitude' => [Rule::requiredIf($captureNow), 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [Rule::requiredIf($captureNow), 'nullable', 'numeric', 'between:-180,180'],
            'geo_accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'geo_source' => [Rule::requiredIf($captureNow), 'nullable', Rule::in(['browser_gps', 'manual_map'])],
            'location_confirmed' => [Rule::requiredIf($captureNow), 'nullable', 'accepted'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'identityCountry' => ['required', Rule::in(['ci'])],
            'identityType' => ['required', Rule::in(['cni', 'passport', 'permis', 'resident'])],
            'identityNumber' => ['required', 'string', 'max:25', 'regex:'.($identityRegex[$identityType] ?? '/^[A-Z0-9-]{6,25}$/'), 'unique:shops,identity_number'],
            'identityUploadMode' => ['required', Rule::in(['pdf', 'scan'])],
            'identityFile' => ['required_if:identityUploadMode,pdf', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'identityFileFront' => ['required_if:identityUploadMode,scan', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'identityFileBack' => ['required_if:identityUploadMode,scan', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'payment_mode' => ['required', Rule::in(['post_delivery', 'weekly'])],
            'mmOperator' => ['required', Rule::in(['orange', 'mtn', 'wave', 'moov'])],
            'mmNumber' => ['required', 'regex:/^\+?[0-9]{8,15}$/'],
            'mmHolder' => ['required', 'string', 'max:255'],
        ]);
    }

    private function normalizePhone(string $country, string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $countryDigits = preg_replace('/\D+/', '', $country) ?? '';

        return '+'.$countryDigits.ltrim($digits, '0');
    }

    private function storeDocuments(Request $request, Shop $shop): void
    {
        $files = [
            'selfie' => ['selfie', 'kyc/selfies'],
            'identityFile' => ['identity_file', 'kyc/identity'],
            'identityFileFront' => ['identity_file_front', 'kyc/identity'],
            'identityFileBack' => ['identity_file_back', 'kyc/identity'],
            'rccmFile' => ['rccm_file', 'kyc/company'],
            'taxFile' => ['tax_file', 'kyc/company'],
        ];

        foreach ($files as $input => [$column, $directory]) {
            if ($request->hasFile($input)) {
                $shop->{$column} = $request->file($input)->store($directory, 'local');
            }
        }

        $shop->save();
    }
}
