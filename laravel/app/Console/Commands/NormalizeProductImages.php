<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\ProductImageNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class NormalizeProductImages extends Command
{
    protected $signature = 'products:normalize-images
        {--product= : Identifiant du produit à traiter}
        {--image= : Identifiant précis de l’image à traiter}
        {--force : Retraiter les images déjà normalisées}';

    protected $description = 'Génère les images produit carrées OVANIE et signale les sources non conformes.';

    public function handle(ProductImageNormalizer $normalizer): int
    {
        if (! Schema::hasTable('product_images')) {
            $this->error('La table product_images est introuvable.');
            return self::FAILURE;
        }

        foreach ([
            'original_path',
            'card_path',
            'thumb_path',
            'original_width',
            'original_height',
            'normalized_at',
        ] as $column) {
            if (! Schema::hasColumn('product_images', $column)) {
                $this->error("La colonne {$column} manque. Exécutez : php artisan migrate");
                return self::FAILURE;
            }
        }

        $query = ProductImage::query()->orderBy('id');

        if ($this->option('product')) {
            $query->where('product_id', (int) $this->option('product'));
        }

        if ($this->option('image')) {
            $query->whereKey((int) $this->option('image'));
        }

        if (! $this->option('force')) {
            $query->where(function ($builder) {
                $builder->whereNull('card_path')
                    ->orWhereNull('thumb_path')
                    ->orWhereNull('normalized_at');
            });
        }

        $images = $query->get();

        if ($images->isEmpty()) {
            $this->info('Aucune image à traiter.');
            return self::SUCCESS;
        }

        $success = 0;
        $rejected = 0;
        $failed = 0;
        $disk = Storage::disk((string) config('product-images.disk', 'public'));

        foreach ($images as $image) {
            $sourcePath = $this->firstExistingSource($image, $disk);

            if ($sourcePath === null) {
                $failed++;
                $this->newLine();
                $this->error("Image #{$image->id} : source introuvable.");
                continue;
            }

            try {
                $result = $normalizer->normalizeStoredPath($sourcePath, 'products');

                $oldVariants = array_filter([
                    $image->path,
                    $image->card_path,
                    $image->thumb_path,
                ]);

                $image->forceFill([
                    'original_path' => $image->original_path ?: $sourcePath,
                    'path' => $result['master'],
                    'card_path' => $result['card'],
                    'thumb_path' => $result['thumb'],
                    'original_width' => $result['original_width'],
                    'original_height' => $result['original_height'],
                    'normalized_at' => now(),
                ])->saveQuietly();

                foreach ($oldVariants as $oldPath) {
                    $clean = $this->cleanStoragePath((string) $oldPath);
                    $sourceClean = $this->cleanStoragePath($sourcePath);
                    $newPaths = array_map(
                        fn ($path) => $this->cleanStoragePath((string) $path),
                        [$result['master'], $result['card'], $result['thumb']]
                    );

                    if ($clean !== ''
                        && $clean !== $sourceClean
                        && ! in_array($clean, $newPaths, true)
                        && $disk->exists($clean)) {
                        $disk->delete($clean);
                    }
                }

                $success++;
                $this->line("Image #{$image->id} : normalisée.");
            } catch (InvalidArgumentException $exception) {
                $rejected++;
                $this->warn(
                    "Image #{$image->id} du produit #{$image->product_id} à remplacer : "
                    . $exception->getMessage()
                );
            } catch (Throwable $exception) {
                $failed++;
                $this->error(
                    "Image #{$image->id} : échec — {$exception->getMessage()}"
                );
            }
        }

        $this->newLine();
        $this->info("Normalisées : {$success}");
        $this->warn("À remplacer car non conformes : {$rejected}");
        $this->error("Échecs techniques : {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function firstExistingSource(ProductImage $image, $disk): ?string
    {
        foreach ([$image->original_path, $image->path, $image->image_path] as $path) {
            $clean = $this->cleanStoragePath((string) $path);

            if ($clean !== '' && $disk->exists($clean)) {
                return $clean;
            }
        }

        return null;
    }

    private function cleanStoragePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = preg_replace('#^(https?:)?//[^/]+/storage/#i', '', $path) ?: $path;
        $path = preg_replace('#^/?storage/#', '', $path) ?: $path;
        $path = preg_replace('#^/?public/#', '', $path) ?: $path;

        return ltrim($path, '/');
    }
}
