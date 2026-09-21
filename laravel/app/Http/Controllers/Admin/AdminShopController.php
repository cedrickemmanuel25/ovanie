<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                    $builder->whereNull('kyc_status')->orWhere('kyc_status', '!=', Shop::KYC_VERIFIED);
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

        if (! empty($data['managed_by_commercial_id']) && ! User::whereKey($data['managed_by_commercial_id'])->where('role', 'commercial')->exists()) {
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
        $shop->delete();

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
