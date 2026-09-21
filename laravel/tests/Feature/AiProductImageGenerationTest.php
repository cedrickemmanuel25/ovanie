<?php

namespace Tests\Feature;

use App\Jobs\GenerateAiProductImageJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\User;
use App\Services\AiProductImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiProductImageGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique test',
            'slug' => 'boutique-test-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'latitude' => 5.35, 'longitude' => -4.01,
            'logistics_status' => 'ready', 'logistics_type' => 'ovanie',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);
        $category = Category::query()->create(['name' => 'Cat ' . uniqid(), 'slug' => 'cat-' . uniqid(), 'status' => 'actif']);

        return Product::query()->create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit test', 'slug' => 'produit-test-' . uniqid(),
            'description' => 'x', 'price' => 1000, 'stock' => 5,
            'status' => 'approved', 'is_active' => true,
        ]);
    }

    public function test_ai_generation_is_disabled_by_default_and_does_not_dispatch_a_job(): void
    {
        config(['product-images.ai_enhancement_enabled' => false]);
        Queue::fake();

        $product = $this->makeProduct();
        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/normalized/x/master.webp',
            'original_path' => 'products/originals/x.jpg',
            'normalized_at' => now(),
            'is_main' => true,
        ]);

        Queue::assertNotPushed(GenerateAiProductImageJob::class);
    }

    public function test_ai_generation_is_skipped_when_no_api_key_even_if_enabled(): void
    {
        config(['product-images.ai_enhancement_enabled' => true, 'services.openai.key' => null]);
        Queue::fake();

        $product = $this->makeProduct();
        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/normalized/x/master.webp',
            'original_path' => 'products/originals/x.jpg',
            'normalized_at' => now(),
            'is_main' => true,
        ]);

        Queue::assertNotPushed(GenerateAiProductImageJob::class);
    }

    public function test_ai_generation_dispatches_a_job_when_enabled_and_configured(): void
    {
        config(['product-images.ai_enhancement_enabled' => true, 'services.openai.key' => 'sk-test']);
        Queue::fake();

        $product = $this->makeProduct();
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/normalized/x/master.webp',
            'original_path' => 'products/originals/x.jpg',
            'normalized_at' => now(),
            'is_main' => true,
        ]);

        Queue::assertPushed(GenerateAiProductImageJob::class, fn ($job) => $job->productImageId === $image->id);
    }

    public function test_ai_generation_is_not_dispatched_for_images_without_normalization(): void
    {
        config(['product-images.ai_enhancement_enabled' => true, 'services.openai.key' => 'sk-test']);
        Queue::fake();

        $product = $this->makeProduct();
        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/raw-upload.jpg',
            'is_main' => true,
        ]);

        Queue::assertNotPushed(GenerateAiProductImageJob::class);
    }

    public function test_generator_falls_back_gracefully_when_openai_request_fails(): void
    {
        config(['product-images.ai_enhancement_enabled' => true, 'services.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'rate limited'], 429)]);

        $product = $this->makeProduct();
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/normalized/x/master.webp',
            'original_path' => 'products/originals/x.jpg',
            'normalized_at' => now(),
            'is_main' => true,
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->put('products/originals/x.jpg', 'fake-binary');

        $result = app(AiProductImageGenerator::class)->generate($image->fresh());

        $this->assertFalse($result);
        $this->assertSame('failed', $image->fresh()->ai_image_status);
        $this->assertSame('products/normalized/x/master.webp', $image->fresh()->path);
    }
}
