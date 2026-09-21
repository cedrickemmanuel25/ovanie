<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    /**
     * Afficher la page des paramètres
     */
    public function edit()
    {
        $settings = [
            'siteName' => Setting::getValue('siteName', 'OVANIE Marketplace'),
            'contactEmail' => Setting::getValue('contactEmail', 'contact@ovanie.com'),
            'hours' => Setting::getValue('hours', 'Du lundi au vendredi, 8h - 18h'),
            'phone' => Setting::getValue('phone', '05 55 55 55 55'),
            'address' => Setting::getValue('address', "Abidjan, Côte d'Ivoire"),
            'siteLogo' => Setting::getValue('siteLogo', 'images/logo.png'),
            'cartIcon' => Setting::getValue('cartIcon', 'images/panier.png'),
            'aiIcon' => Setting::getValue('aiIcon', 'images/ia.png'),
            'trustedSellerIcon' => Setting::getValue('trustedSellerIcon', 'images/svg.png'),
            'deliveryIcon' => Setting::getValue('deliveryIcon', 'images/svg-copie.png'),
            'paymentIcon' => Setting::getValue('paymentIcon', 'images/svg-e.png'),
            'supportIcon' => Setting::getValue('supportIcon', 'images/svg-copie-2.png'),
            'returnIcon' => Setting::getValue('returnIcon', 'images/svg-2.png'),
            'workerIcon' => Setting::getValue('workerIcon', 'images/ouvrier1.png'),
            'giftLeftIcon' => Setting::getValue('giftLeftIcon', 'images/cadeau-a-gauche.png'),
            'giftRightIcon' => Setting::getValue('giftRightIcon', 'images/cadeau-a-droit.png'),
            'flashIcon' => Setting::getValue('flashIcon', 'images/flash-panier.png'),
            'businessIcon' => Setting::getValue('businessIcon', 'images/ovanie-business.png'),
            'featuredIcon' => Setting::getValue('featuredIcon', 'images/a-la-une.png'),
            'blackFridayIcon' => Setting::getValue('blackFridayIcon', 'images/flame_transparent.png'),
            'classicSaleIcon' => Setting::getValue('classicSaleIcon', 'images/panier.png'),
            'productsIcon' => Setting::getValue('productsIcon', 'images/nos-produit.png'),
            'placeholderImage' => Setting::getValue('placeholderImage', 'images/placeholder.png'),
            'announcement1' => Setting::getValue('announcement1', 'Votre chantier commence ici'),
            'announcement2' => Setting::getValue('announcement2', "Livraison partout en Côte d'Ivoire"),
            'homeTopAdsTitle' => Setting::getValue('homeTopAdsTitle', 'Publicités en vedette'),
            'homeTopAdsJson' => Setting::getValue('homeTopAdsJson', '[]'),
            'maintenanceMode' => Setting::getValue('maintenanceMode', '0'),
            'bankName' => Setting::getValue('bankName', 'NSIA Banque'),
            'bankAccountName' => Setting::getValue('bankAccountName', 'OVANIE SARL'),
            'bankAccountNumber' => Setting::getValue('bankAccountNumber', '1234567890'),
        ];

        return view('admin.settings', compact('settings'));
    }

    /**
     * Mettre à jour les paramètres
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'siteName' => ['required', 'string', 'max:255'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'hours' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],

            'announcement1' => ['nullable', 'string', 'max:255'],
            'announcement2' => ['nullable', 'string', 'max:255'],

            'homeTopAdsTitle' => ['nullable', 'string', 'max:255'],
            'homeTopAdsJson' => ['nullable', 'string'],

            'bankName' => ['required', 'string', 'max:255'],
            'bankAccountName' => ['required', 'string', 'max:255'],
            'bankAccountNumber' => ['required', 'string', 'max:100'],

            'siteLogo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'cartIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'aiIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'trustedSellerIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'deliveryIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'paymentIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'supportIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'returnIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'workerIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'giftLeftIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'giftRightIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'flashIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'businessIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'featuredIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'blackFridayIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'classicSaleIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'productsIcon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'placeholderImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'maintenanceMode' => ['nullable', 'boolean'],
        ]);

        $validated = $this->normalizeTextFields($validated);

        $validated['homeTopAdsJson'] = $this->validateAndNormalizeAdsJson(
            $validated['homeTopAdsJson'] ?? '[]'
        );

        $imageFields = [
            'siteLogo' => 'logos',
            'cartIcon' => 'site-icons',
            'aiIcon' => 'site-icons',
            'trustedSellerIcon' => 'site-icons',
            'deliveryIcon' => 'site-icons',
            'paymentIcon' => 'site-icons',
            'supportIcon' => 'site-icons',
            'returnIcon' => 'site-icons',
            'workerIcon' => 'site-icons',
            'giftLeftIcon' => 'site-icons',
            'giftRightIcon' => 'site-icons',
            'flashIcon' => 'site-icons',
            'businessIcon' => 'site-icons',
            'featuredIcon' => 'site-icons',
            'blackFridayIcon' => 'site-icons',
            'classicSaleIcon' => 'site-icons',
            'productsIcon' => 'site-icons',
            'placeholderImage' => 'site-icons',
        ];

        foreach ($imageFields as $field => $directory) {
            if ($request->hasFile($field)) {
                $validated[$field] = $this->handleImageUpload($request, $field, $directory);
            }
        }

        $keysToSave = [
            'siteName',
            'contactEmail',
            'hours',
            'phone',
            'address',
            'announcement1',
            'announcement2',
            'homeTopAdsTitle',
            'homeTopAdsJson',
            'bankName',
            'bankAccountName',
            'bankAccountNumber',
            'siteLogo',
            'cartIcon',
            'aiIcon',
            'trustedSellerIcon',
            'deliveryIcon',
            'paymentIcon',
            'supportIcon',
            'returnIcon',
            'workerIcon',
            'giftLeftIcon',
            'giftRightIcon',
            'flashIcon',
            'businessIcon',
            'featuredIcon',
            'blackFridayIcon',
            'classicSaleIcon',
            'productsIcon',
            'placeholderImage',
        ];

        foreach ($keysToSave as $key) {
            if (array_key_exists($key, $validated)) {
                Setting::setValue($key, $validated[$key]);
            }
        }

        Setting::setValue(
            'maintenanceMode',
            $request->boolean('maintenanceMode') ? '1' : '0'
        );

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Paramètres mis à jour avec succès.');
    }

    /**
     * Nettoyer les champs texte simples
     */
    protected function normalizeTextFields(array $data): array
    {
        $fields = [
            'siteName',
            'contactEmail',
            'hours',
            'phone',
            'address',
            'announcement1',
            'announcement2',
            'homeTopAdsTitle',
            'bankName',
            'bankAccountName',
            'bankAccountNumber',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Valider et normaliser le JSON des pubs accueil
     */
    protected function validateAndNormalizeAdsJson(?string $json): string
    {
        $json = trim((string) $json);

        if ($json === '') {
            return '[]';
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw ValidationException::withMessages([
                'homeTopAdsJson' => 'Le format JSON des pubs accueil est invalide.',
            ]);
        }

        $normalizedAds = [];

        foreach ($decoded as $index => $ad) {
            if (!is_array($ad)) {
                throw ValidationException::withMessages([
                    'homeTopAdsJson' => 'Chaque publicité doit être un objet JSON valide.',
                ]);
            }

            $type = strtolower(trim((string) ($ad['type'] ?? 'image')));

            if (!in_array($type, ['image', 'video'], true)) {
                throw ValidationException::withMessages([
                    'homeTopAdsJson' => "La publicité #" . ($index + 1) . " a un type invalide. Utilisez uniquement 'image' ou 'video'.",
                ]);
            }

            $media = trim((string) ($ad['media'] ?? ''));
            if ($media === '') {
                throw ValidationException::withMessages([
                    'homeTopAdsJson' => "La publicité #" . ($index + 1) . " doit contenir un champ 'media'.",
                ]);
            }

            $duration = isset($ad['duration']) ? (int) $ad['duration'] : 5000;
            if ($duration < 1000) {
                $duration = 1000;
            }

            $normalizedAds[] = [
                'type' => $type,
                'title' => trim((string) ($ad['title'] ?? '')),
                'text' => trim((string) ($ad['text'] ?? '')),
                'media' => $media,
                'link' => trim((string) ($ad['link'] ?? '')),
                'duration' => $duration,
                'active' => isset($ad['active']) ? (bool) $ad['active'] : true,
            ];
        }

        return json_encode(
            $normalizedAds,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }

    /**
     * Gérer l’upload d’une image de paramètre.
     */
    protected function handleImageUpload(Request $request, string $field, string $directory): string
    {
        $file = $request->file($field);

        $oldImage = Setting::getValue($field);
        if ($oldImage && !str_starts_with($oldImage, 'images/') && Storage::disk('public')->exists($oldImage)) {
            Storage::disk('public')->delete($oldImage);
        }

        return $file->store($directory, 'public');
    }
}