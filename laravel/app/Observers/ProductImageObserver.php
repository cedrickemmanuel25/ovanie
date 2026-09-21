<?php

namespace App\Observers;

use App\Jobs\GenerateAiProductImageJob;
use App\Models\ProductImage;
use App\Services\AiProductImageGenerator;
use Illuminate\Support\Facades\DB;

class ProductImageObserver
{
    /**
     * Déclenche la génération IA uniquement pour les photos qui viennent de
     * passer par ProductImageNormalizer (normalized_at renseigné) — c'est-à-dire
     * les vraies photos envoyées par un vendeur ou un commercial, pas les
     * clones/imports internes qui réutilisent déjà une image existante.
     */
    public function created(ProductImage $image): void
    {
        if (! $image->normalized_at) {
            return;
        }

        if (! app(AiProductImageGenerator::class)->isEnabled()) {
            return;
        }

        DB::afterCommit(function () use ($image) {
            GenerateAiProductImageJob::dispatch($image->id);
        });
    }
}
