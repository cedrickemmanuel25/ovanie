<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\AiProductImageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiProductImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(public int $productImageId)
    {
    }

    public function handle(AiProductImageGenerator $generator): void
    {
        $image = ProductImage::find($this->productImageId);

        if (! $image) {
            return;
        }

        $generator->generate($image);
    }
}
