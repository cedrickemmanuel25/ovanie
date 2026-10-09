<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/** Contrôles techniques préalables à une vérification d'identité par un prestataire. */
class AutomatedKycDocumentInspector
{
    public function inspect(Request $request): void
    {
        $images = [
            'selfie' => [480, 480],
            'identityFileFront' => [640, 400],
            'identityFileBack' => [640, 400],
        ];
        $hashes = [];

        foreach ($images as $field => [$minWidth, $minHeight]) {
            $file = $request->file($field);
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $size = @getimagesize($file->getRealPath());
            if (! $size || $size[0] < $minWidth || $size[1] < $minHeight) {
                throw ValidationException::withMessages([
                    $field => "Image illisible ou trop petite : résolution minimale {$minWidth} × {$minHeight} pixels.",
                ]);
            }
            $hashes[$field] = hash_file('sha256', $file->getRealPath());
        }

        if (isset($hashes['identityFileFront'], $hashes['identityFileBack'])
            && hash_equals($hashes['identityFileFront'], $hashes['identityFileBack'])) {
            throw ValidationException::withMessages([
                'identityFileBack' => 'Le recto et le verso ne peuvent pas être le même fichier.',
            ]);
        }

        foreach (['identityFile', 'rccmFile', 'taxFile'] as $field) {
            $file = $request->file($field);
            if (! $file instanceof UploadedFile
                || ($file->getMimeType() !== 'application/pdf'
                    && strtolower($file->getClientOriginalExtension()) !== 'pdf')) {
                continue;
            }
            $handle = @fopen($file->getRealPath(), 'rb');
            if (! $handle) {
                throw ValidationException::withMessages([$field => 'Ce PDF est illisible.']);
            }
            $header = fread($handle, 5);
            $length = filesize($file->getRealPath());
            fseek($handle, -min(2048, max(1, $length)), SEEK_END);
            $tail = stream_get_contents($handle);
            fclose($handle);
            if ($header !== '%PDF-' || ! str_contains((string) $tail, '%%EOF')) {
                throw ValidationException::withMessages([
                    $field => 'Ce PDF est incomplet ou corrompu. Téléversez un document complet.',
                ]);
            }
        }
    }
}
