<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\SellerLogisticsValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VendorShopProfileController extends Controller
{
    /**
     * Affiche le profil boutique vendeur.
     */
    public function show()
    {
        $shop = $this->currentShop();
        $documents = $this->documentsStatus($shop);

        return view('vendor.shop-profile', compact('shop', 'documents'));
    }

    /**
     * Affiche le formulaire de modification du profil boutique.
     */
    public function edit()
    {
        $shop = $this->currentShop();

        return view('vendor.shop-edit', compact('shop'));
    }

    /**
     * Affiche une page dédiée à la méthode de reversement vendeur.
     */
    public function paymentMethod()
    {
        $shop = $this->currentShop();

        return view('vendor.payment-method', compact('shop'));
    }

    /**
     * Met à jour uniquement la méthode de paiement/reversement vendeur.
     */
    public function updatePaymentMethod(Request $request)
    {
        $shop = $this->currentShop();

        $validated = $request->validate([
            'direct_payment' => ['nullable', 'boolean'],
            'mm_operator' => ['required', Rule::in(['orange', 'mtn', 'wave', 'moov'])],
            'mm_number' => ['required', 'regex:/^[0-9+ ]{8,20}$/'],
            'mm_holder' => ['required', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'business_email' => ['nullable', 'email', 'max:255'],
        ], [
            'mm_operator.required' => 'Choisissez l’opérateur de paiement.',
            'mm_number.required' => 'Le numéro Mobile Money est obligatoire.',
            'mm_number.regex' => 'Le numéro Mobile Money est invalide.',
            'mm_holder.required' => 'Le nom du titulaire est obligatoire.',
            'business_email.email' => 'L’email professionnel est invalide.',
        ]);

        $shop->fill([
            'direct_payment' => $request->boolean('direct_payment'),
            'mm_operator' => $validated['mm_operator'],
            'mm_number' => $this->cleanPhone($validated['mm_number']),
            'mm_holder' => $validated['mm_holder'],
            'whatsapp' => $this->cleanPhone($validated['whatsapp'] ?? $shop->whatsapp),
            'business_email' => $validated['business_email'] ?? $shop->business_email,
        ])->save();

        return redirect()
            ->route($this->vendorRoutePrefix() . '.payouts.index')
            ->with('success', 'Votre méthode de paiement vendeur a été mise à jour.');
    }

    /**
     * Met à jour les informations commerciales de la boutique.
     */
    public function update(Request $request)
    {
        $shop = $this->currentShop();

        // Le mode logistique est défini à l'ouverture de la boutique.
        $request->merge([
            'logistics_type' => $shop->logistics_type ?: 'ovanie',
        ]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('shops', 'name')->ignore($shop->id),
            ],
            'description' => ['required', 'string', 'max:700'],
            'main_category' => [
                'required',
                Rule::in([
                    'ciment-beton',
                    'fer-metaux',
                    'carrelage',
                    'peinture',
                    'isolation',
                    'electricite',
                    'plomberie',
                    'bois',
                    'outillage',
                    'solaire',
                    'quincaillerie',
                ]),
            ],
            'delivery_zone' => [
                'required',
                Rule::in(['abidjan', 'grand_abidjan', 'national', 'afrique_ouest']),
            ],
            'processing_time' => [
                'required',
                Rule::in(['lt24h', '24_48h', '3_5j', '7j_plus']),
            ],
            'logistics_type' => ['nullable', Rule::in(['ovanie', 'seller'])],
            'region' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'commune' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required_if:logistics_type,ovanie', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:logistics_type,ovanie', 'nullable', 'numeric', 'between:-180,180'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'direct_payment' => ['nullable', 'boolean'],
            'mm_operator' => ['required', Rule::in(['orange', 'mtn', 'wave', 'moov'])],
            'mm_number' => ['required', 'regex:/^[0-9+ ]{8,20}$/'],
            'mm_holder' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Le nom de la boutique est obligatoire.',
            'name.unique' => 'Ce nom de boutique est déjà utilisé.',
            'description.required' => 'La description est obligatoire.',
            'main_category.required' => 'La catégorie principale est obligatoire.',
            'delivery_zone.required' => 'La zone de livraison est obligatoire.',
            'processing_time.required' => 'Le délai de traitement est obligatoire.',
            'city.required' => 'La ville est obligatoire.',
            'commune.required' => 'La commune est obligatoire.',
            'district.required' => 'Le quartier ou district est obligatoire.',
            'address.required' => 'L’adresse du point d’enlèvement est obligatoire.',
            'latitude.required_if' => 'La position GPS exacte de la boutique est obligatoire avec OVANIE Logistique.',
            'longitude.required_if' => 'La position GPS exacte de la boutique est obligatoire avec OVANIE Logistique.',
            'business_email.email' => 'L’email professionnel est invalide.',
            'logo.image' => 'Le logo doit être une image.',
            'logo.max' => 'Le logo ne doit pas dépasser 3 Mo.',
            'mm_operator.required' => 'L’opérateur Mobile Money est obligatoire.',
            'mm_number.required' => 'Le numéro Mobile Money est obligatoire.',
            'mm_number.regex' => 'Le numéro Mobile Money est invalide.',
            'mm_holder.required' => 'Le nom du titulaire Mobile Money est obligatoire.',
        ]);

        DB::transaction(function () use ($request, $shop, $validated) {
            $shop->fill([
                'name' => $validated['name'],
                'slug' => $this->generateUniqueSlug($validated['name'], $shop->id),
                'description' => $validated['description'],
                'main_category' => $validated['main_category'],
                'delivery_zone' => $validated['delivery_zone'],
                'processing_time' => $validated['processing_time'],
                'logistics_type' => $shop->logistics_type ?: 'ovanie',
                'region' => $validated['region'],
                'city' => $validated['city'],
                'commune' => $validated['commune'],
                'district' => $validated['district'],
                'landmark' => $validated['landmark'] ?? null,
                'address' => $validated['address'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'whatsapp' => $this->cleanPhone($validated['whatsapp'] ?? null),
                'business_email' => $validated['business_email'] ?? null,
                'direct_payment' => $request->boolean('direct_payment'),
                'mm_operator' => $validated['mm_operator'] ?? null,
                'mm_number' => $this->cleanPhone($validated['mm_number'] ?? null),
                'mm_holder' => $validated['mm_holder'] ?? null,
            ]);

            if ($request->hasFile('logo')) {
                $this->deletePublicFile($shop->logo);
                $shop->logo = $request->file('logo')->store('shops/logos', 'public');
            }

            $shop->save();
        });

        app(SellerLogisticsValidator::class)->synchronizeShopStatus($shop->fresh());

        return redirect()
            ->route('vendor.shop.profile')
            ->with('success', 'Le profil de votre boutique a été mis à jour avec succès.');
    }

    /**
     * Affiche la page de suivi et correction des documents vendeur.
     */
    public function documents()
    {
        $shop = $this->currentShop();
        $documents = $this->documentsStatus($shop);

        return view('vendor.shop-documents', compact('shop', 'documents'));
    }

    /**
     * Met à jour un document précis depuis la page Documents de la boutique.
     *
     * Le nouveau formulaire utilise document_type afin qu'un remplacement de
     * RCCM, de document fiscal ou de selfie n'oblige pas le vendeur à renvoyer
     * toute sa pièce d'identité.
     *
     * La branche "legacy" reste conservée pour compatibilité avec un ancien
     * formulaire qui enverrait encore directement identity_file, rccm_file, etc.
     */
    public function updateDocuments(Request $request)
    {
        $shop = $this->currentShop();

        if ($request->filled('document_type')) {
            $this->updateSingleDocument($request, $shop);

            return redirect()
                ->route('vendor.shop.documents')
                ->with('success', 'Le document a été envoyé. Le dossier sera revérifié par OVANIE.');
        }

        $this->updateLegacyDocumentBatch($request, $shop);

        return redirect()
            ->route('vendor.shop.documents')
            ->with('success', 'Vos documents ont été mis à jour. Le dossier sera revérifié par OVANIE.');
    }

    /**
     * Upload ciblé d'un seul type de document.
     */
    private function updateSingleDocument(Request $request, Shop $shop): void
    {
        $type = (string) $request->input('document_type');

        $request->validate([
            'document_type' => [
                'required',
                Rule::in(['rccm', 'identity_pdf', 'identity_scan', 'tax', 'selfie']),
            ],
            'document_name' => ['nullable', 'string', 'max:120'],
        ]);

        if ($type === 'identity_scan') {
            $request->validate([
                'identity_file_front' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
                'identity_file_back' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            ], [
                'identity_file_front.required' => 'Le recto de la pièce d’identité est obligatoire.',
                'identity_file_back.required' => 'Le verso de la pièce d’identité est obligatoire.',
            ]);
        } elseif ($type === 'identity_pdf') {
            $request->validate([
                'document_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            ], [
                'document_file.required' => 'Choisissez le PDF de la pièce d’identité.',
                'document_file.mimes' => 'La pièce d’identité doit être un PDF.',
            ]);
        } elseif ($type === 'selfie') {
            $request->validate([
                'document_file' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            ], [
                'document_file.required' => 'Choisissez une photo du vendeur.',
                'document_file.image' => 'Le selfie doit être une image.',
            ]);
        } else {
            $request->validate([
                'document_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ], [
                'document_file.required' => 'Choisissez un document à envoyer.',
                'document_file.mimes' => 'Le fichier doit être au format PDF, JPG ou PNG.',
            ]);
        }

        DB::transaction(function () use ($request, $shop, $type) {
            if ($type === 'rccm') {
                $this->deleteLocalFile($shop->rccm_file);
                $shop->rccm_file = $request->file('document_file')->store('kyc/company', 'local');
            }

            if ($type === 'tax') {
                $this->deleteLocalFile($shop->tax_file);
                $shop->tax_file = $request->file('document_file')->store('kyc/company', 'local');
            }

            if ($type === 'selfie') {
                $this->deleteLocalFile($shop->selfie);
                $shop->selfie = $request->file('document_file')->store('kyc/selfies', 'local');
            }

            if ($type === 'identity_pdf') {
                $this->deleteLocalFile($shop->identity_file);
                $this->deleteLocalFile($shop->identity_file_front);
                $this->deleteLocalFile($shop->identity_file_back);

                $shop->identity_upload_mode = 'pdf';
                $shop->identity_file = $request->file('document_file')->store('kyc/identity', 'local');
                $shop->identity_file_front = null;
                $shop->identity_file_back = null;
            }

            if ($type === 'identity_scan') {
                $this->deleteLocalFile($shop->identity_file);
                $this->deleteLocalFile($shop->identity_file_front);
                $this->deleteLocalFile($shop->identity_file_back);

                $shop->identity_upload_mode = 'scan';
                $shop->identity_file = null;
                $shop->identity_file_front = $request->file('identity_file_front')->store('kyc/identity', 'local');
                $shop->identity_file_back = $request->file('identity_file_back')->store('kyc/identity', 'local');
            }

            // Une mise à jour documentaire relance uniquement la vérification KYC.
            // Elle ne doit pas fermer la boutique commercialement.
            $shop->kyc_status = Shop::KYC_PENDING;
            $shop->save();
        });
    }

    /**
     * Compatibilité avec l'ancien formulaire multi-documents.
     */
    private function updateLegacyDocumentBatch(Request $request, Shop $shop): void
    {
        $hasExistingPdf = filled($shop->identity_file);
        $hasExistingScan = filled($shop->identity_file_front) && filled($shop->identity_file_back);

        $mode = (string) ($request->input('identity_upload_mode') ?: $shop->identity_upload_mode ?: 'pdf');

        $rules = [
            'selfie' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'identity_upload_mode' => ['nullable', Rule::in(['pdf', 'scan'])],
            'identity_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'identity_file_front' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'identity_file_back' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'rccm_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'tax_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];

        if ($mode === 'pdf' && ! $hasExistingPdf && ! $request->hasFile('identity_file')) {
            $rules['identity_file'][] = 'required';
        }

        if ($mode === 'scan' && ! $hasExistingScan) {
            if (! $request->hasFile('identity_file_front')) {
                $rules['identity_file_front'][] = 'required';
            }

            if (! $request->hasFile('identity_file_back')) {
                $rules['identity_file_back'][] = 'required';
            }
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $shop, $mode) {
            $shop->identity_upload_mode = $mode;

            if ($request->hasFile('selfie')) {
                $this->deleteLocalFile($shop->selfie);
                $shop->selfie = $request->file('selfie')->store('kyc/selfies', 'local');
            }

            if ($request->hasFile('identity_file')) {
                $this->deleteLocalFile($shop->identity_file);
                $this->deleteLocalFile($shop->identity_file_front);
                $this->deleteLocalFile($shop->identity_file_back);

                $shop->identity_upload_mode = 'pdf';
                $shop->identity_file = $request->file('identity_file')->store('kyc/identity', 'local');
                $shop->identity_file_front = null;
                $shop->identity_file_back = null;
            }

            if ($request->hasFile('identity_file_front') || $request->hasFile('identity_file_back')) {
                $this->deleteLocalFile($shop->identity_file);

                if ($request->hasFile('identity_file_front')) {
                    $this->deleteLocalFile($shop->identity_file_front);
                    $shop->identity_file_front = $request->file('identity_file_front')->store('kyc/identity', 'local');
                }

                if ($request->hasFile('identity_file_back')) {
                    $this->deleteLocalFile($shop->identity_file_back);
                    $shop->identity_file_back = $request->file('identity_file_back')->store('kyc/identity', 'local');
                }

                $shop->identity_upload_mode = 'scan';
                $shop->identity_file = null;
            }

            if ($request->hasFile('rccm_file')) {
                $this->deleteLocalFile($shop->rccm_file);
                $shop->rccm_file = $request->file('rccm_file')->store('kyc/company', 'local');
            }

            if ($request->hasFile('tax_file')) {
                $this->deleteLocalFile($shop->tax_file);
                $shop->tax_file = $request->file('tax_file')->store('kyc/company', 'local');
            }

            $shop->kyc_status = Shop::KYC_PENDING;
            $shop->save();
        });
    }

    private function vendorRoutePrefix(): string
    {
        return request()->routeIs('daniel.*') ? 'daniel' : 'vendor';
    }

    private function currentShop(): Shop
    {
        $shop = Auth::user()?->shop;

        abort_unless($shop, 403, 'Vous devez avoir une boutique pour accéder à cette page.');

        return $shop;
    }

    private function documentsStatus(Shop $shop): array
    {
        return [
            'selfie' => [
                'label' => 'Selfie vendeur',
                'uploaded' => filled($shop->selfie),
                'path' => $shop->selfie,
            ],
            'identity_pdf' => [
                'label' => 'Pièce identité PDF',
                'uploaded' => filled($shop->identity_file),
                'path' => $shop->identity_file,
            ],
            'identity_front' => [
                'label' => 'Pièce identité recto',
                'uploaded' => filled($shop->identity_file_front),
                'path' => $shop->identity_file_front,
            ],
            'identity_back' => [
                'label' => 'Pièce identité verso',
                'uploaded' => filled($shop->identity_file_back),
                'path' => $shop->identity_file_back,
            ],
            'rccm' => [
                'label' => 'Document RCCM',
                'uploaded' => filled($shop->rccm_file),
                'path' => $shop->rccm_file,
            ],
            'tax' => [
                'label' => 'Document fiscal',
                'uploaded' => filled($shop->tax_file),
                'path' => $shop->tax_file,
            ],
        ];
    }

    private function generateUniqueSlug(string $name, int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'boutique';
        $slug = $base;
        $i = 2;

        while (Shop::where('slug', $slug)->where('id', '!=', $ignoreId)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function cleanPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        return preg_replace('/\s+/', '', $phone);
    }

    private function deleteLocalFile(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function deletePublicFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
