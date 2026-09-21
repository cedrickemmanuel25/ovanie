<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ProductImageNormalizer
{
    /**
     * Stocke l'original et génère les variantes carrées OVANIE.
     *
     * @return array{
     *     original:string,
     *     master:string,
     *     card:string,
     *     thumb:string,
     *     original_width:int,
     *     original_height:int,
     *     output_format:string
     * }
     */
    public function normalize(
        UploadedFile $file,
        string $directory = 'products'
    ): array {
        if (! $file->isValid()) {
            throw new RuntimeException('Le fichier image envoyé est invalide.');
        }

        $this->assertGdAvailable();

        $diskName = (string) config('product-images.disk', 'public');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $extension = in_array($extension, ['jpg', 'png', 'webp'], true)
            ? $extension
            : 'jpg';

        $originalPath = trim($directory, '/')
            . '/originals/'
            . now()->format('Y/m')
            . '/'
            . Str::uuid()
            . '.'
            . $extension;

        $stored = Storage::disk($diskName)->putFileAs(
            dirname($originalPath),
            $file,
            basename($originalPath)
        );

        if (! is_string($stored) || $stored === '') {
            throw new RuntimeException('Impossible d’enregistrer l’image originale.');
        }

        try {
            return [
                'original' => $stored,
                ...$this->normalizeStoredPath($stored, $directory),
            ];
        } catch (Throwable $exception) {
            Storage::disk($diskName)->delete($stored);
            throw $exception;
        }
    }

    /**
     * Renormalise un fichier déjà présent dans storage/app/public.
     *
     * @return array{
     *     master:string,
     *     card:string,
     *     thumb:string,
     *     original_width:int,
     *     original_height:int,
     *     output_format:string
     * }
     */
    public function normalizeStoredPath(
        string $storedPath,
        string $directory = 'products'
    ): array {
        $this->assertGdAvailable();

        $disk = Storage::disk((string) config('product-images.disk', 'public'));
        $cleanPath = $this->cleanStoragePath($storedPath);

        if ($cleanPath === '' || ! $disk->exists($cleanPath)) {
            throw new RuntimeException("Image source introuvable : {$storedPath}");
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'ovanie-product-source-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Impossible de créer un fichier temporaire.');
        }

        try {
            $stream = $disk->readStream($cleanPath);
            $target = fopen($temporaryPath, 'wb');

            if (! is_resource($stream) || ! is_resource($target)) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if (is_resource($target)) {
                    fclose($target);
                }

                throw new RuntimeException('Impossible de lire l’image source.');
            }

            stream_copy_to_stream($stream, $target);
            fclose($stream);
            fclose($target);

            return $this->normalizeAbsolutePath($temporaryPath, $directory);
        } finally {
            @unlink($temporaryPath);
        }
    }

    /**
     * Supprime uniquement les variantes générées par ce service.
     */
    public function deleteNormalizedSet(array $paths): void
    {
        $disk = Storage::disk((string) config('product-images.disk', 'public'));

        foreach (['master', 'card', 'thumb'] as $key) {
            $path = $this->cleanStoragePath((string) ($paths[$key] ?? ''));

            if ($path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * @return array{
     *     master:string,
     *     card:string,
     *     thumb:string,
     *     original_width:int,
     *     original_height:int,
     *     output_format:string
     * }
     */
    private function normalizeAbsolutePath(
        string $absolutePath,
        string $directory
    ): array {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException('Le fichier image source est illisible.');
        }

        $imageInfo = @getimagesize($absolutePath);

        if (! is_array($imageInfo)) {
            throw new RuntimeException('Le fichier envoyé n’est pas une image exploitable.');
        }

        $originalWidth = (int) ($imageInfo[0] ?? 0);
        $originalHeight = (int) ($imageInfo[1] ?? 0);
        $mime = strtolower((string) ($imageInfo['mime'] ?? ''));

        $this->assertSourceIsMarketplaceReady(
            $originalWidth,
            $originalHeight,
            $mime
        );

        $maxPixels = max(
            1,
            (int) config('product-images.max_source_pixels', 40_000_000)
        );

        if (($originalWidth * $originalHeight) > $maxPixels) {
            throw new RuntimeException('L’image est trop grande pour être traitée en sécurité.');
        }

        $binary = @file_get_contents($absolutePath);
        $source = is_string($binary) ? @imagecreatefromstring($binary) : false;

        if (! $source instanceof GdImage) {
            throw new RuntimeException('Impossible de décoder l’image.');
        }

        $result = [];

        try {
            $source = $this->applyExifOrientation($source, $absolutePath, $mime);
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);

            $basePath = trim($directory, '/')
                . '/normalized/'
                . now()->format('Y/m')
                . '/'
                . Str::uuid();

            foreach ($this->configuredSizes() as $variant => $size) {
                $result[$variant] = $this->renderVariant(
                    $source,
                    $sourceWidth,
                    $sourceHeight,
                    $size,
                    "{$basePath}/{$variant}.webp"
                );
            }

            return [
                'master' => $result['master'],
                'card' => $result['card'],
                'thumb' => $result['thumb'],
                'original_width' => $originalWidth,
                'original_height' => $originalHeight,
                'output_format' => 'webp',
            ];
        } catch (Throwable $exception) {
            $this->deleteNormalizedSet($result);
            throw $exception;
        } finally {
            imagedestroy($source);
        }
    }

    private function assertSourceIsMarketplaceReady(
        int $width,
        int $height,
        string $mime
    ): void {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Les dimensions de l’image sont invalides.');
        }

        $allowedMimeTypes = (array) config('product-images.allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);

        if (! in_array($mime, $allowedMimeTypes, true)) {
            throw new InvalidArgumentException(
                'Format non pris en charge. Utilisez JPG, PNG ou WEBP.'
            );
        }

        $minimumWidth = max(1, (int) config('product-images.minimum_width', 800));
        $minimumHeight = max(1, (int) config('product-images.minimum_height', 800));

        if ($width < $minimumWidth || $height < $minimumHeight) {
            throw new InvalidArgumentException(
                "L’image mesure {$width} × {$height} px. "
                . "Minimum requis : {$minimumWidth} × {$minimumHeight} px."
            );
        }

        $ratio = $width / max(1, $height);
        $minimumRatio = (float) config('product-images.minimum_ratio', 0.75);
        $maximumRatio = (float) config('product-images.maximum_ratio', 1.333333);

        if ($ratio < $minimumRatio || $ratio > $maximumRatio) {
            throw new InvalidArgumentException(
                "L’image {$width} × {$height} px est trop verticale ou trop horizontale. "
                . 'Le format doit être compris entre 3:4 et 4:3.'
            );
        }
    }

    private function renderVariant(
        GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        int $canvasSize,
        string $outputPath
    ): string {
        $canvas = imagecreatetruecolor($canvasSize, $canvasSize);

        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('Impossible de créer la toile carrée.');
        }

        try {
            [$red, $green, $blue] = $this->backgroundRgb();
            $background = imagecolorallocate($canvas, $red, $green, $blue);
            imagefill($canvas, 0, 0, $background);

            $paddingRatio = min(
                0.20,
                max(0.0, (float) config('product-images.padding_ratio', 0.055))
            );
            $padding = (int) round($canvasSize * $paddingRatio);
            $available = max(1, $canvasSize - ($padding * 2));

            $scale = min(
                $available / max(1, $sourceWidth),
                $available / max(1, $sourceHeight)
            );

            $destinationWidth = max(1, (int) round($sourceWidth * $scale));
            $destinationHeight = max(1, (int) round($sourceHeight * $scale));
            $destinationX = (int) floor(($canvasSize - $destinationWidth) / 2);
            $destinationY = (int) floor(($canvasSize - $destinationHeight) / 2);

            $copied = imagecopyresampled(
                $canvas,
                $source,
                $destinationX,
                $destinationY,
                0,
                0,
                $destinationWidth,
                $destinationHeight,
                $sourceWidth,
                $sourceHeight
            );

            if (! $copied) {
                throw new RuntimeException('La mise à l’échelle de l’image a échoué.');
            }

            $quality = min(
                100,
                max(60, (int) config('product-images.webp_quality', 88))
            );

            ob_start();
            $encoded = imagewebp($canvas, null, $quality);
            $webpBinary = ob_get_clean();

            if (! $encoded || ! is_string($webpBinary) || $webpBinary === '') {
                throw new RuntimeException('L’encodage WEBP a échoué.');
            }

            $disk = Storage::disk((string) config('product-images.disk', 'public'));

            if (! $disk->put($outputPath, $webpBinary)) {
                throw new RuntimeException('L’enregistrement de l’image a échoué.');
            }

            return $outputPath;
        } finally {
            imagedestroy($canvas);
        }
    }

    /** @return array<string,int> */
    private function configuredSizes(): array
    {
        $sizes = (array) config('product-images.sizes', [
            'master' => 1200,
            'card' => 800,
            'thumb' => 400,
        ]);

        return [
            'master' => max(400, (int) ($sizes['master'] ?? 1200)),
            'card' => max(300, (int) ($sizes['card'] ?? 800)),
            'thumb' => max(150, (int) ($sizes['thumb'] ?? 400)),
        ];
    }

    /** @return array{0:int,1:int,2:int} */
    private function backgroundRgb(): array
    {
        $background = (array) config('product-images.background', [248, 250, 252]);

        return [
            min(255, max(0, (int) ($background[0] ?? 248))),
            min(255, max(0, (int) ($background[1] ?? 250))),
            min(255, max(0, (int) ($background[2] ?? 252))),
        ];
    }

    private function applyExifOrientation(
        GdImage $source,
        string $absolutePath,
        string $mime
    ): GdImage {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $source;
        }

        $exif = @exif_read_data($absolutePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $rotated = null;

        if ($orientation === 3) {
            $rotated = imagerotate($source, 180, 0);
        } elseif ($orientation === 6) {
            $rotated = imagerotate($source, -90, 0);
        } elseif ($orientation === 8) {
            $rotated = imagerotate($source, 90, 0);
        }

        if ($rotated instanceof GdImage) {
            imagedestroy($source);
            return $rotated;
        }

        return $source;
    }

    private function cleanStoragePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = preg_replace('#^(https?:)?//[^/]+/storage/#i', '', $path) ?: $path;
        $path = preg_replace('#^/?storage/#', '', $path) ?: $path;
        $path = preg_replace('#^/?public/#', '', $path) ?: $path;

        return ltrim($path, '/');
    }

    private function assertGdAvailable(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            throw new RuntimeException(
                'L’extension PHP GD avec le support WEBP doit être activée.'
            );
        }
    }
}
