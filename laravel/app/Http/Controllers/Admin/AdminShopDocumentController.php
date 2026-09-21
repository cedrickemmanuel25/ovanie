<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminShopDocumentController extends Controller
{
    /**
     * Liste blanche des documents KYC consultables par l'admin.
     * La clé est utilisée dans l'URL, la colonne correspond au champ de la table shops.
     */
    private const DOCUMENTS = [
        'identity-pdf' => [
            'field' => 'identity_file',
            'label' => 'piece-identite-pdf',
        ],
        'identity-front' => [
            'field' => 'identity_file_front',
            'label' => 'piece-identite-recto',
        ],
        'identity-back' => [
            'field' => 'identity_file_back',
            'label' => 'piece-identite-verso',
        ],
        'selfie' => [
            'field' => 'selfie',
            'label' => 'selfie-vendeur',
        ],
        'rccm' => [
            'field' => 'rccm_file',
            'label' => 'rccm',
        ],
        'tax' => [
            'field' => 'tax_file',
            'label' => 'document-fiscal',
        ],
    ];

    /**
     * Affiche un document KYC privé dans le navigateur.
     */
    public function show(Shop $shop, string $document): BinaryFileResponse
    {
        $resolved = $this->resolveDocument($shop, $document);

        return response()->file($resolved['path'], [
            'Content-Type' => $resolved['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Télécharge un document KYC privé.
     */
    public function download(Shop $shop, string $document): BinaryFileResponse
    {
        $resolved = $this->resolveDocument($shop, $document);

        return response()->download(
            $resolved['path'],
            $resolved['filename'],
            [
                'Content-Type' => $resolved['mime'],
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]
        );
    }

    /**
     * Résout un document uniquement depuis la liste blanche.
     */
    private function resolveDocument(Shop $shop, string $document): array
    {
        abort_unless(array_key_exists($document, self::DOCUMENTS), 404, 'Document inconnu.');

        $config = self::DOCUMENTS[$document];
        $field = $config['field'];
        $storedPath = $shop->{$field};

        abort_if(blank($storedPath), 404, 'Document non disponible pour cette boutique.');

        $resolved = $this->resolveStoragePath($storedPath);

        abort_unless($resolved, 404, 'Fichier introuvable dans le stockage Laravel.');

        $extension = pathinfo($resolved['path'], PATHINFO_EXTENSION) ?: 'file';
        $filename = $config['label'] . '-boutique-' . $shop->id . '.' . $extension;

        return [
            'path' => $resolved['path'],
            'mime' => mime_content_type($resolved['path']) ?: 'application/octet-stream',
            'filename' => $filename,
        ];
    }

    /**
     * Compatible avec les fichiers privés sur local et les anciens fichiers sur public.
     */
    private function resolveStoragePath(string $storedPath): ?array
    {
        if (Storage::disk('local')->exists($storedPath)) {
            return [
                'disk' => 'local',
                'path' => Storage::disk('local')->path($storedPath),
            ];
        }

        return null;
    }
}
