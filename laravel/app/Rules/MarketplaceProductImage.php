<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class MarketplaceProductImage implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Le fichier envoyé doit être une image valide.');
            return;
        }

        $realPath = $value->getRealPath();

        if (! is_string($realPath) || $realPath === '') {
            $fail('Impossible de lire cette image.');
            return;
        }

        $imageInfo = @getimagesize($realPath);

        if (! is_array($imageInfo)) {
            $fail('Le fichier envoyé n’est pas une image exploitable.');
            return;
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        $mimeType = strtolower((string) ($imageInfo['mime'] ?? $value->getMimeType() ?? ''));

        $allowedMimeTypes = (array) config('product-images.allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);

        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            $fail('Format non accepté. Utilisez une image JPG, PNG ou WEBP.');
            return;
        }

        $minimumWidth = max(1, (int) config('product-images.minimum_width', 800));
        $minimumHeight = max(1, (int) config('product-images.minimum_height', 800));

        if ($width < $minimumWidth || $height < $minimumHeight) {
            $fail(
                "L’image mesure {$width} × {$height} px. "
                . "Elle doit mesurer au minimum {$minimumWidth} × {$minimumHeight} px."
            );
            return;
        }

        $ratio = $width / max(1, $height);
        $minimumRatio = (float) config('product-images.minimum_ratio', 0.75);
        $maximumRatio = (float) config('product-images.maximum_ratio', 1.333333);

        if ($ratio < $minimumRatio || $ratio > $maximumRatio) {
            $fail(
                "Cette photo ({$width} × {$height} px) est trop verticale ou trop horizontale. "
                . 'Utilisez une photo carrée ou proche du carré, comprise entre les formats 3:4 et 4:3, '
                . 'avec le produit entièrement visible.'
            );
        }
    }
}
