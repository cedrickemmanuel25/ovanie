<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\Geo\AdminGoogleMapsLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use ZipArchive;

class AdminShopController extends Controller
{
    /**
     * Liste des boutiques vendeurs avec filtres admin.
     */
    public function index(Request $request)
    {
        $query = Shop::query()
            ->with(['user', 'reviewer', 'commercialCreator', 'commercialManager'])
            ->withCount('products');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . trim((string) $request->input('city')) . '%');
        }

        if ($request->filled('type')) {
            $query->where('seller_type', $request->input('type'));
        }

        if ($request->filled('readiness')) {
            match ($request->input('readiness')) {
                'kyc_pending' => $query->where(function ($builder) {
                    $builder->whereNull('kyc_status')->orWhereNotIn('kyc_status', [Shop::KYC_VERIFIED, Shop::KYC_AUTOMATED_CHECKED]);
                }),
                'gps_missing' => $query->where(function ($builder) {
                    $builder->whereNull('latitude')->orWhereNull('longitude');
                }),
                'logistics_incomplete' => $query->where(function ($builder) {
                    $builder->whereNull('logistics_status')->orWhere('logistics_status', '!=', Shop::LOGISTICS_READY);
                }),
                'publishable' => $query->canPublishProducts(),
                default => null,
            };
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('company_name', 'like', '%' . $search . '%')
                    ->orWhere('rccm', 'like', '%' . $search . '%')
                    ->orWhere('taxpayer_number', 'like', '%' . $search . '%')
                    ->orWhere('commune', 'like', '%' . $search . '%')
                    ->orWhere('district', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    });
            });
        }

        match ($request->get('sort', 'recent')) {
            'old' => $query->oldest(),
            'az' => $query->orderBy('name'),
            'za' => $query->orderByDesc('name'),
            'pending_first' => $query
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest(),
            default => $query->latest(),
        };

        $shops = $query->paginate(8)->withQueryString();

        $stats = [
            'all' => Shop::count(),
            'pending' => Shop::where('status', 'pending')->count(),
            'approved' => Shop::where('status', Shop::STATUS_APPROVED)->count(),
            'gps_missing' => Shop::where(function ($builder) {
                $builder->whereNull('latitude')->orWhereNull('longitude');
            })->count(),
            'logistics_incomplete' => Shop::where(function ($builder) {
                $builder->whereNull('logistics_status')->orWhere('logistics_status', '!=', Shop::LOGISTICS_READY);
            })->count(),
        ];

        $commercials = User::query()
            ->where('role', 'commercial')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.shops.index', compact('shops', 'stats', 'commercials'));
    }

    public function updateCommercialManager(Request $request, Shop $shop)
    {
        $data = $request->validate([
            'managed_by_commercial_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (! empty($data['managed_by_commercial_id']) && ! User::whereKey($data['managed_by_commercial_id'])->where('role', 'commercial')->where('status', 'active')->exists()) {
            return back()->with('error', 'Le gestionnaire sélectionné doit être un commercial actif.');
        }

        $shop->update(['managed_by_commercial_id' => $data['managed_by_commercial_id'] ?? null]);

        return back()->with('success', 'L’attribution commerciale de la boutique a été mise à jour.');
    }

    /**
     * Détail d'une boutique vendeur pour validation.
     */
    public function show(Shop $shop)
    {
        $shop->load([
            'user',
            'reviewer',
            'commercialCreator',
            'commercialManager',
            'sellerDeliveryProfile',
            'sellerDeliveryZones',
            'sellerDeliveryCapacities',
        ])->loadCount(['products', 'orders']);

        return view('admin.shops.show', compact('shop'));
    }

    /**
     * Formulaire administratif de configuration opérationnelle.
     * L'administrateur peut régulariser une ancienne boutique sans utiliser le GPS
     * de son propre appareil. Pour OVANIE Logistics, il colle uniquement une
     * référence Google Maps ; latitude/longitude restent des données techniques.
     */
    public function operationalConfiguration(Shop $shop)
    {
        $shop->load([
            'user',
            'sellerDeliveryProfile',
            'sellerDeliveryZones',
        ]);

        $sellerZone = $shop->sellerDeliveryZones
            ->sortByDesc(fn ($zone) => (bool) $zone->is_active)
            ->first();

        return view('admin.shops.operational-configuration', compact('shop', 'sellerZone'));
    }

    /**
     * Enregistre la logistique et le reversement au nom du vendeur.
     */
    public function updateOperationalConfiguration(
        Request $request,
        Shop $shop,
        AdminGoogleMapsLocationService $locationResolver
    ) {
        $logisticsType = (string) $request->input('logistics_type');
        $isOvanie = $logisticsType === 'ovanie';
        $isSeller = $logisticsType === 'seller';
        $needsNewReference = $isOvanie && ($shop->latitude === null || $shop->longitude === null);

        $data = $request->validate([
            'logistics_type' => ['required', Rule::in(['ovanie', 'seller'])],

            'google_maps_reference' => [
                'nullable',
                Rule::requiredIf($needsNewReference),
                'string',
                'max:1000',
            ],
            'address' => ['nullable', Rule::requiredIf($isOvanie), 'string', 'max:255'],
            'commune' => ['nullable', Rule::requiredIf($isOvanie), 'string', 'max:120'],
            'district' => ['nullable', Rule::requiredIf($isOvanie), 'string', 'max:120'],
            'landmark' => ['nullable', 'string', 'max:255'],

            'seller_default_delay' => ['nullable', Rule::requiredIf($isSeller), 'string', 'max:120'],
            'seller_conditions' => ['nullable', Rule::requiredIf($isSeller), 'string', 'max:2000'],
            'seller_max_weight_kg' => ['nullable', Rule::requiredIf($isSeller), 'numeric', 'gt:0'],
            'seller_max_volume_m3' => ['nullable', Rule::requiredIf($isSeller), 'numeric', 'gt:0'],
            'seller_zone_commune' => ['nullable', Rule::requiredIf($isSeller), 'string', 'max:120'],
            'seller_zone_price' => ['nullable', Rule::requiredIf($isSeller), 'numeric', 'min:0'],
            'seller_zone_delay' => ['nullable', Rule::requiredIf($isSeller), 'string', 'max:120'],

            'payment_mode' => ['required', Rule::in([Shop::PAYMENT_POST_DELIVERY, Shop::PAYMENT_WEEKLY])],
            'mm_operator' => ['required', Rule::in(['orange', 'mtn', 'wave', 'moov'])],
            'mm_number' => ['required', 'regex:/^[0-9+ ]{8,20}$/'],
            'mm_holder' => ['required', 'string', 'max:255'],
            'admin_confirmation' => ['required', 'accepted'],
        ], [
            'google_maps_reference.required' => 'Collez la position Google Maps de la boutique. Aucune carte ni GPS administrateur n’est utilisé.',
            'address.required' => 'Renseignez l’adresse ou le point de retrait de la boutique.',
            'commune.required' => 'Renseignez la commune de la boutique.',
            'district.required' => 'Renseignez le quartier de la boutique.',
            'seller_default_delay.required' => 'Renseignez le délai habituel de livraison du vendeur.',
            'seller_conditions.required' => 'Renseignez les conditions de livraison du vendeur.',
            'seller_zone_commune.required' => 'Renseignez au moins une zone de livraison.',
            'seller_zone_price.required' => 'Renseignez le tarif de cette zone.',
            'seller_zone_delay.required' => 'Renseignez le délai de cette zone.',
            'admin_confirmation.accepted' => 'Confirmez que ces informations ont été communiquées ou vérifiées avec le vendeur.',
        ]);

        $adminId = Auth::guard('admin')->id() ?: auth()->id();

        DB::transaction(function () use ($shop, $data, $isOvanie, $locationResolver, $adminId) {
            if ($isOvanie) {
                $location = null;
                $reference = trim((string) ($data['google_maps_reference'] ?? ''));

                if ($reference !== '') {
                    $location = $locationResolver->resolve(
                        $reference,
                        $data['address'] ?? null,
                        $data['commune'] ?? null,
                        $data['district'] ?? null,
                        $data['landmark'] ?? null,
                    );
                } elseif ($shop->latitude !== null && $shop->longitude !== null) {
                    $location = [
                        'latitude' => (float) $shop->latitude,
                        'longitude' => (float) $shop->longitude,
                        'address' => $data['address'] ?? $shop->address,
                        'commune' => $data['commune'] ?? $shop->commune,
                        'district' => $data['district'] ?? $shop->district,
                        'landmark' => $data['landmark'] ?? $shop->landmark,
                        'region' => $shop->region,
                        'city' => $shop->city,
                        'source' => $shop->geo_source ?: 'admin_google_maps',
                        'precision' => $shop->geo_precision ?: 'high',
                        'precision_score' => $shop->geo_precision_score ?: 90,
                    ];
                }

                $score = (int) ($location['precision_score'] ?? 0);
                $geoStatus = $score >= 85
                    ? Shop::GEO_STATUS_VERIFIED
                    : ($score >= 60 ? Shop::GEO_STATUS_RELIABLE : Shop::GEO_STATUS_REVIEW_RECOMMENDED);

                $shop->forceFill([
                    'logistics_type' => 'ovanie',
                    'address' => trim((string) ($location['address'] ?? $data['address'] ?? '')),
                    'commune' => trim((string) ($location['commune'] ?? $data['commune'] ?? '')),
                    'district' => trim((string) ($location['district'] ?? $data['district'] ?? '')),
                    'landmark' => trim((string) ($location['landmark'] ?? $data['landmark'] ?? '')) ?: null,
                    'region' => trim((string) ($location['region'] ?? $shop->region ?? '')) ?: $shop->region,
                    'city' => trim((string) ($location['city'] ?? $shop->city ?? '')) ?: $shop->city,
                    'latitude' => (float) $location['latitude'],
                    'longitude' => (float) $location['longitude'],
                    'geo_accuracy' => null,
                    'geo_source' => (string) ($location['source'] ?? 'admin_google_maps'),
                    'geo_precision' => (string) ($location['precision'] ?? 'high'),
                    'geo_precision_score' => $score ?: 90,
                    'geo_status' => $geoStatus,
                    'geo_verified_at' => now(),
                    'admin_google_maps_reference' => $reference !== '' ? $reference : $shop->admin_google_maps_reference,
                    'logistics_status' => Shop::LOGISTICS_READY,
                    'logistics_confirmed_at' => now(),
                    'logistics_configured_by_admin_id' => $adminId,
                    'logistics_configured_by_admin_at' => now(),
                ])->save();

                $shop->sellerDeliveryProfile()->update([
                    'is_enabled' => false,
                    'status' => 'disabled',
                ]);
                $shop->sellerDeliveryZones()->update(['is_active' => false]);
            } else {
                $profile = $shop->sellerDeliveryProfile()->updateOrCreate(
                    ['shop_id' => $shop->id],
                    [
                        'is_enabled' => true,
                        'default_delay' => trim((string) $data['seller_default_delay']),
                        'max_weight_kg' => (float) $data['seller_max_weight_kg'],
                        'max_volume_m3' => (float) $data['seller_max_volume_m3'],
                        'conditions' => trim((string) $data['seller_conditions']),
                        'status' => 'ready',
                        'validated_at' => now(),
                        'validated_by' => $adminId,
                    ]
                );

                $zone = $shop->sellerDeliveryZones()->oldest('id')->first();
                $zonePayload = [
                    'shop_id' => $shop->id,
                    'seller_delivery_profile_id' => $profile->id,
                    'city' => $shop->city,
                    'commune' => trim((string) $data['seller_zone_commune']),
                    'district' => null,
                    'delivery_price' => (float) $data['seller_zone_price'],
                    'estimated_delay' => trim((string) $data['seller_zone_delay']),
                    'max_weight_kg' => (float) $data['seller_max_weight_kg'],
                    'max_volume_m3' => (float) $data['seller_max_volume_m3'],
                    'is_active' => true,
                ];

                if ($zone) {
                    $zone->update($zonePayload);
                } else {
                    $shop->sellerDeliveryZones()->create($zonePayload);
                }

                $shop->forceFill([
                    'logistics_type' => 'seller',
                    'logistics_status' => Shop::LOGISTICS_READY,
                    'logistics_confirmed_at' => now(),
                    'logistics_configured_by_admin_id' => $adminId,
                    'logistics_configured_by_admin_at' => now(),
                ])->save();
            }

            $shop->forceFill([
                'payment_mode' => $data['payment_mode'],
                'mm_operator' => $data['mm_operator'],
                'mm_number' => preg_replace('/\s+/', '', trim((string) $data['mm_number'])),
                'mm_holder' => trim((string) $data['mm_holder']),
                'payout_configured_at' => now(),
                'payout_configured_by_admin_id' => $adminId,
                'payout_configured_by_admin_at' => now(),
            ])->save();
        });

        $shop->refresh();

        return redirect()
            ->route('admin.shops.show', $shop)
            ->with('success', $shop->configurationReady()
                ? 'Logistique et reversement configurés par l’administration. La boutique est opérationnellement régularisée.'
                : 'La configuration a été enregistrée. Certaines informations restent à compléter.');
    }

    /**
     * Met à jour le statut de validation d'une boutique.
     *
     * Règles métier :
     * - approved  => is_active = true, approved_at rempli, rejection_reason vidé
     * - pending   => is_active = false, approved_at vidé
     * - rejected  => is_active = false, approved_at vidé, raison de rejet conservée/remplie
     */
    public function updateStatus(Request $request, Shop $shop)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ], [
            'status.required' => 'Le statut est obligatoire.',
            'status.in' => 'Le statut sélectionné est invalide.',
            'rejection_reason.max' => 'La raison du rejet ne doit pas dépasser 2000 caractères.',
        ]);

        $status = $validated['status'];

        $payload = [
            'status' => $status,
            'reviewed_by' => Auth::guard('admin')->id() ?: auth()->id(),
        ];

        if ($status === 'approved') {
            $payload['is_active'] = true;
            $payload['approved_at'] = now();
            $payload['rejection_reason'] = null;
        }

        if ($status === 'pending') {
            $payload['is_active'] = false;
            $payload['approved_at'] = null;
            $payload['rejection_reason'] = null;
        }

        if ($status === 'rejected') {
            $payload['is_active'] = false;
            $payload['approved_at'] = null;
            $payload['rejection_reason'] = $validated['rejection_reason']
                ?: $shop->rejection_reason
                ?: 'Votre dossier vendeur a été rejeté. Merci de corriger vos informations ou documents, puis de soumettre à nouveau votre demande.';
        }

        $shop->update($payload);

        if ($shop->user) {
            $userPayload = ['role' => 'vendor'];

            if ($status === 'approved') {
                $userPayload['status'] = 'active';
            }

            $shop->user->forceFill($userPayload)->save();
        }

        $message = match ($status) {
            'approved' => 'Boutique approuvée et activée avec succès.',
            'rejected' => 'Boutique rejetée avec succès.',
            default => 'Boutique remise en attente de validation.',
        };

        return back()->with('success', $message);
    }

    public function requireConfiguration(Request $request, Shop $shop)
    {
        $data = $request->validate([
            'configure_logistics' => ['nullable', 'boolean'],
            'configure_payout' => ['nullable', 'boolean'],
        ]);

        $logistics = $request->boolean('configure_logistics');
        $payout = $request->boolean('configure_payout');

        if (! $logistics && ! $payout) {
            return back()->with('error', 'Choisissez au moins une configuration à faire confirmer par le vendeur.');
        }

        $changes = [];
        if ($logistics) {
            $changes['logistics_confirmed_at'] = null;
            $changes['logistics_status'] = Shop::LOGISTICS_INCOMPLETE;
        }
        if ($payout) {
            $changes['payout_configured_at'] = null;
        }

        $shop->forceFill($changes)->save();

        return back()->with('success', 'La boutique sera dirigée vers la régularisation obligatoire lors de la prochaine connexion vendeur.');
    }

    /**
     * Télécharge le dossier complet vendeur dans un ZIP.
     * Compatible avec les fichiers privés stockés sur local et les fichiers publics.
     */
    public function downloadDossier(Shop $shop)
    {
        $files = [
            'piece_identite_pdf' => $shop->identity_file,
            'piece_identite_recto' => $shop->identity_file_front,
            'piece_identite_verso' => $shop->identity_file_back,
            'rccm' => $shop->rccm_file,
            'compte_contribuable' => $shop->tax_file,
            'photo_vendeur' => $shop->selfie,
            'logo_boutique' => $shop->logo,
        ];

        $shopName = Str::slug($shop->name ?: 'boutique-' . $shop->id);
        $zipFileName = 'dossier-vendeur-' . $shopName . '.zip';
        $tempDirectory = storage_path('app/temp');
        $zipPath = $tempDirectory . DIRECTORY_SEPARATOR . $zipFileName;

        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0755, true);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Impossible de créer le dossier ZIP. Vérifiez que l’extension ZipArchive est activée.');
        }

        $addedFiles = 0;

        foreach ($files as $label => $file) {
            $resolved = $this->resolveStorageFile($file);

            if (! $resolved) {
                continue;
            }

            $extension = pathinfo($resolved['path'], PATHINFO_EXTENSION) ?: 'file';
            $zip->addFile($resolved['path'], $label . '.' . $extension);
            $addedFiles++;
        }

        $zip->close();

        if ($addedFiles === 0) {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            return back()->with('error', 'Aucun document vendeur disponible pour cette boutique.');
        }

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    /**
     * Supprime une boutique.
     */
    public function destroy(Shop $shop)
    {
        // Une boutique qui a déjà des commandes, des produits vendus ou des paiements reste liée à cet historique :
        // la base refuse sa suppression. On l'explique au lieu d'afficher une erreur technique.
        try {
            \Illuminate\Support\Facades\DB::transaction(fn () => $shop->delete());
        } catch (\Illuminate\Database\QueryException $exception) {
            return back()->with('error', 'Cette boutique ne peut pas être supprimée : elle est liée à des commandes, des produits ou des paiements. Vous pouvez la suspendre à la place.');
        }

        return redirect()
            ->route('admin.shops.index')
            ->with('success', 'Boutique supprimée.');
    }

    /**
     * Retrouve un fichier soit sur le disque local privé, soit sur le disque public.
     */
    private function resolveStorageFile(?string $file): ?array
    {
        if (! $file) {
            return null;
        }

        if (Storage::disk('local')->exists($file)) {
            return [
                'disk' => 'local',
                'path' => Storage::disk('local')->path($file),
            ];
        }

        if (Storage::disk('public')->exists($file)) {
            return [
                'disk' => 'public',
                'path' => Storage::disk('public')->path($file),
            ];
        }

        return null;
    }
}
