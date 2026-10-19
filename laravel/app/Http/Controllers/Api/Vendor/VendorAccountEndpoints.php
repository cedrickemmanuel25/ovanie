<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\VendorProductController;
use App\Http\Controllers\VendorShopProfileController;
use App\Http\Controllers\VendorShopStatusController;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\SellerLogisticsValidator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

/**
 * Écrans « compte et boutique » de l'application OVANIE Vendeur : état de la
 * boutique, documents, sécurité du compte, export du catalogue et bon de
 * préparation. Chaque endpoint réutilise la logique de l'espace vendeur Web.
 */
trait VendorAccountEndpoints
{
    private const DOCUMENT_HINTS = [
        'selfie' => 'Photo nette de votre visage, sans lunettes de soleil. JPG ou PNG, 5 Mo max.',
        'identity_pdf' => 'Pièce d’identité complète en un seul PDF. 10 Mo max.',
        'identity_front' => 'Recto lisible de la pièce d’identité. JPG ou PNG, 5 Mo max.',
        'identity_back' => 'Verso lisible de la pièce d’identité. JPG ou PNG, 5 Mo max.',
        'rccm' => 'Registre du commerce (RCCM) de l’entreprise. PDF, JPG ou PNG, 5 Mo max.',
        'tax' => 'Attestation fiscale ou numéro de compte contribuable. PDF, JPG ou PNG, 5 Mo max.',
    ];

    private const DOCUMENT_LABELS = [
        'selfie' => 'Selfie du vendeur',
        'identity_pdf' => 'Pièce d’identité (PDF)',
        'identity_front' => 'Pièce d’identité — recto',
        'identity_back' => 'Pièce d’identité — verso',
        'rccm' => 'Registre du commerce (RCCM)',
        'tax' => 'Document fiscal',
    ];

    private const DOCUMENT_COLUMNS = [
        'selfie' => 'selfie',
        'identity_pdf' => 'identity_file',
        'identity_front' => 'identity_file_front',
        'identity_back' => 'identity_file_back',
        'rccm' => 'rccm_file',
        'tax' => 'tax_file',
    ];

    public function shopStatus(Request $request, SellerLogisticsValidator $validator): JsonResponse
    {
        $shop = $this->shopFor($request);
        $summary = app(VendorShopStatusController::class)->summary($shop, $validator);
        $shop = $summary['shop']->fresh();
        $logistics = $summary['logisticsValidation'];

        $products = Product::query()->where('shop_id', $shop->id)->notArchived();

        return response()->json([
            'status' => [
                'label' => $summary['statusLabel'],
                'description' => $summary['statusDescription'],
                'tone' => $summary['statusTone'],
            ],
            'commercial_open' => (bool) $summary['commercialOpen'],
            'can_publish' => (bool) $summary['canPublish'],
            'rejection_reason' => $shop->status === Shop::STATUS_REJECTED ? $shop->rejection_reason : null,
            'steps' => collect($summary['steps'])->values()->map(fn (array $step, int $index) => $step + [
                'key' => ['shop', 'documents', 'logistics', 'publication'][$index] ?? 'step',
            ]),
            'checks' => [
                ['label' => 'Boutique', 'value' => $shop->status === Shop::STATUS_APPROVED ? 'Validée par OVANIE' : ($shop->status === Shop::STATUS_REJECTED ? 'Restreinte' : 'En cours de validation'), 'done' => $shop->status === Shop::STATUS_APPROVED],
                ['label' => 'Ouverture commerciale', 'value' => $summary['commercialOpen'] ? 'Ouverte aux clients' : 'Fermée aux clients', 'done' => (bool) $summary['commercialOpen']],
                ['label' => 'Documents', 'value' => $this->kycLabel($shop), 'done' => in_array($shop->kyc_status, [Shop::KYC_VERIFIED, Shop::KYC_AUTOMATED_CHECKED], true)],
                ['label' => 'Logistique', 'value' => ($logistics['complete'] ?? false) ? 'Configuration complète' : 'À compléter', 'done' => (bool) ($logistics['complete'] ?? false)],
                ['label' => 'Position de la boutique', 'value' => $shop->geo_status_label, 'done' => in_array($shop->geo_status, [Shop::GEO_STATUS_VERIFIED, Shop::GEO_STATUS_RELIABLE], true)],
            ],
            'logistics_missing' => array_values($logistics['missing'] ?? []),
            'products' => [
                'total' => (clone $products)->count(),
                'active' => (clone $products)->where('is_active', true)->count(),
            ],
            'member_since' => $shop->created_at?->toIso8601String(),
            'approved_at' => $shop->approved_at?->toIso8601String(),
        ]);
    }

    public function shopDocuments(Request $request): JsonResponse
    {
        $shop = $this->shopFor($request);
        $documents = app(VendorShopProfileController::class)->documentsStatus($shop);
        $scan = filled($shop->identity_file_front) || filled($shop->identity_file_back);

        $rows = collect($documents)->map(function (array $doc, string $key) use ($shop, $scan) {
            $path = (string) ($doc['path'] ?? '');
            $required = match ($key) {
                'selfie' => true,
                'identity_pdf' => ! $scan,
                'identity_front', 'identity_back' => $scan,
                default => false,
            };

            return [
                'key' => $key,
                'label' => self::DOCUMENT_LABELS[$key] ?? $doc['label'],
                'hint' => self::DOCUMENT_HINTS[$key] ?? null,
                'uploaded' => (bool) $doc['uploaded'],
                'required' => $required,
                'group' => in_array($key, ['rccm', 'tax'], true) ? 'company' : 'identity',
                'file_type' => $path === '' ? null : (str_ends_with(strtolower($path), '.pdf') ? 'pdf' : 'image'),
                'updated_at' => $doc['uploaded'] ? $shop->updated_at?->toIso8601String() : null,
            ];
        })->values();

        return response()->json([
            'kyc_status' => $shop->kyc_status,
            'kyc_label' => $this->kycLabel($shop),
            'kyc_description' => match ($shop->kyc_status) {
                Shop::KYC_VERIFIED => 'Vos documents ont été vérifiés par OVANIE.',
                Shop::KYC_AUTOMATED_CHECKED => 'Vos fichiers ont passé les contrôles automatiques. L’équipe OVANIE finalise la vérification.',
                Shop::KYC_REJECTED => 'Un ou plusieurs documents ont été refusés. Envoyez une nouvelle version lisible.',
                default => 'Ajoutez les documents demandés pour faire vérifier votre boutique.',
            },
            'identity_mode' => $scan ? 'scan' : 'pdf',
            'documents' => $rows,
            'uploaded' => $rows->where('uploaded', true)->count(),
            'required_missing' => $rows->where('required', true)->where('uploaded', false)->count(),
        ]);
    }

    public function shopDocumentFile(Request $request, string $type)
    {
        $shop = $this->shopFor($request);
        $column = self::DOCUMENT_COLUMNS[$type] ?? null;
        abort_unless($column, 404);
        $path = (string) $shop->{$column};
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 404, 'Ce document n’est pas disponible.');

        return Storage::disk('local')->download($path, $type . '.' . pathinfo($path, PATHINFO_EXTENSION));
    }

    public function uploadShopDocument(Request $request): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $request->validate(['document_type' => ['required', 'string']]);

        $response = $this->callController(VendorShopProfileController::class, 'updateDocuments', ['request' => $request]);

        return $this->flashJson($response, 'Document envoyé. OVANIE va le vérifier.');
    }

    public function accountSecurity(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = (int) $user->currentAccessToken()?->id;

        $sessions = $user->tokens()->latest('last_used_at')->latest('created_at')->limit(20)->get()
            ->map(fn ($token) => [
                'id' => 'token:' . (int) $token->id,
                'name' => $this->tokenDeviceLabel((string) $token->name),
                'is_current' => (int) $token->id === $currentId,
                'last_used_at' => ($token->last_used_at ?? $token->created_at)?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])->values();

        return response()->json([
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified' => $user->email_verified_at !== null,
            // Le seul numéro vérifié par code est le numéro WhatsApp.
            'phone_verified' => $user->whatsapp_verified_at !== null
                && preg_replace('/\D/', '', (string) $user->whatsapp_phone) !== ''
                && substr(preg_replace('/\D/', '', (string) $user->whatsapp_phone), -10) === substr(preg_replace('/\D/', '', (string) $user->phone), -10),
            'sessions' => $sessions,
        ]);
    }

    public function changeAccountPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
            'logout_others' => ['sometimes', 'boolean'],
        ], [
            'password.different' => 'Le nouveau mot de passe doit être différent de l’actuel.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $user = $request->user();
        if (! Hash::check((string) $data['current_password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
                'errors' => ['current_password' => ['Le mot de passe actuel est incorrect.']],
            ], 422);
        }

        $user->forceFill(['password' => Hash::make((string) $data['password'])])->save();

        $revoked = 0;
        if ($request->boolean('logout_others')) {
            $revoked = $user->tokens()->whereKeyNot($user->currentAccessToken()?->id)->delete();
        }

        return response()->json([
            'message' => 'Mot de passe modifié.',
            'revoked_sessions' => $revoked,
        ]);
    }

    public function destroyAccountSession(Request $request, string $session): JsonResponse
    {
        $tokenId = str_starts_with($session, 'token:') ? substr($session, 6) : $session;
        abort_unless(ctype_digit($tokenId), 404);
        $token = $request->user()->tokens()->whereKey((int) $tokenId)->firstOrFail();
        abort_if((int) $token->id === (int) $request->user()->currentAccessToken()?->id, 422, 'Utilisez « Se déconnecter » pour cet appareil.');
        $token->delete();

        return response()->json(['message' => 'Appareil déconnecté.']);
    }

    public function exportProducts(Request $request)
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);

        return $this->callController(VendorProductController::class, 'export');
    }

    /**
     * Bon de préparation PDF : même contenu que le détail commande vendeur
     * (articles, mission, collecte), sans donnée client masquée par OVANIE.
     */
    public function orderPreparationSlip(Request $request, Order $order)
    {
        $shop = $this->shopFor($request);
        $payload = app()->call([$this, 'order'], ['request' => $request, 'order' => $order])->getData(true)['order'] ?? [];

        $pdf = Pdf::loadView('pdf.vendor-preparation-slip', [
            'order' => $payload,
            'shop' => $shop,
            'generatedAt' => now(),
        ])->setPaper('a4');

        return $pdf->download('bon-preparation-' . ($payload['order_number'] ?? $order->id) . '.pdf');
    }

    private function kycLabel(Shop $shop): string
    {
        return match ($shop->kyc_status) {
            Shop::KYC_VERIFIED => 'Vérifiés',
            Shop::KYC_AUTOMATED_CHECKED => 'Contrôlés, vérification en cours',
            Shop::KYC_REJECTED => 'À renvoyer',
            default => 'À compléter',
        };
    }

    private function tokenDeviceLabel(string $name): string
    {
        $name = strtolower($name);
        $app = match (true) {
            str_contains($name, 'vendeur') || str_contains($name, 'vendor') => 'OVANIE Vendeur',
            str_contains($name, 'livreur') => 'OVANIE Livreur',
            str_contains($name, 'commercial') => 'OVANIE Pro',
            default => 'Application OVANIE',
        };
        $device = match (true) {
            str_contains($name, 'android') => 'Android',
            str_contains($name, 'ios') || str_contains($name, 'iphone') => 'iPhone',
            str_contains($name, 'web') => 'Navigateur',
            default => 'Appareil mobile',
        };

        return $device . ' — ' . $app;
    }
}
