<?php

namespace Tests\Unit;

use App\Models\MasterProduct;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CommercialQuickProductService;
use ReflectionMethod;
use Tests\TestCase;

class CommercialQuickProductServiceTest extends TestCase
{
    public function test_empty_search_never_returns_the_whole_catalog(): void
    {
        $shop = new Shop();
        $shop->id = 12;

        $result = app(CommercialQuickProductService::class)
            ->searchReferences($shop, '', null, 20);

        $this->assertSame([], $result['data']);
        $this->assertTrue($result['meta']['requires_query']);
        $this->assertSame(2, $result['meta']['min_query_length']);
        $this->assertSame(0, $result['meta']['returned_count']);
    }

    public function test_one_character_search_never_returns_the_whole_catalog(): void
    {
        $shop = new Shop();
        $shop->id = 12;

        $result = app(CommercialQuickProductService::class)
            ->searchReferences($shop, 'c', null, 20);

        $this->assertSame([], $result['data']);
        $this->assertTrue($result['meta']['requires_query']);
        $this->assertSame(0, $result['meta']['returned_count']);
    }

    public function test_complete_master_product_has_no_missing_field(): void
    {
        $master = new MasterProduct([
            'name' => 'Ciment Bélier Classic',
            'category_id' => 1,
            'description' => 'Ciment pour travaux de maçonnerie.',
            'unit' => 'sac',
            'weight_kg' => 50,
            'length_cm' => 60,
            'width_cm' => 40,
            'height_cm' => 15,
            'fragile' => false,
            'requires_unloading' => false,
        ]);

        $missing = app(CommercialQuickProductService::class)->missingMasterFields($master);

        $this->assertSame([], $missing);
    }

    public function test_product_already_online_can_be_checked_for_missing_fields(): void
    {
        $product = new Product([
            'name' => 'Ciment Bélier Classic CPJ 32.5',
            'category_id' => 1,
            'description' => 'Ciment pour travaux de maçonnerie.',
            'unit' => 'sac',
            'weight_kg' => 50,
            'length_cm' => 60,
            'width_cm' => 40,
            'height_cm' => 15,
            'fragile' => false,
            'requires_unloading' => false,
        ]);

        $missing = app(CommercialQuickProductService::class)->missingProductFields($product);

        $this->assertSame([], $missing);
    }

    public function test_search_tokens_tolerate_a_copied_title_with_broken_punctuation(): void
    {
        $service = app(CommercialQuickProductService::class);
        $method = new ReflectionMethod($service, 'searchTokens');

        $tokens = $method->invoke(
            $service,
            'Ciment B?lier Classic CPJ 32,5 - sac de 50 kg'
        );

        $this->assertContains('ciment', $tokens);
        $this->assertContains('classic', $tokens);
        $this->assertContains('cpj', $tokens);
        $this->assertContains('50', $tokens);
    }

    public function test_exact_or_near_exact_product_title_is_ranked_before_an_unrelated_product(): void
    {
        $service = app(CommercialQuickProductService::class);
        $method = new ReflectionMethod($service, 'relevanceScore');

        $matchingScore = $method->invoke(
            $service,
            'Ciment Bélier Classic CPJ 32.5 sac 50 kg',
            'Ciment Bélier Classic CPJ 32.5 - sac de 50 kg',
            'Bélier',
            'CIM-BELIER-50',
            null,
            'Sac 50 kg'
        );

        $unrelatedScore = $method->invoke(
            $service,
            'Ciment Bélier Classic CPJ 32.5 sac 50 kg',
            'Peinture acrylique blanche 20 litres',
            'Autre marque',
            'PEINT-20',
            null,
            'Seau 20 L'
        );

        $this->assertLessThan($unrelatedScore, $matchingScore);
    }

    public function test_product_attributes_are_copied_from_master_and_volume_is_recalculated(): void
    {
        $service = app(CommercialQuickProductService::class);
        $method = new ReflectionMethod($service, 'productAttributes');

        $master = new MasterProduct([
            'name' => 'Ciment Bélier Classic',
            'category_id' => 4,
            'sku' => 'CIM-BEL-50',
            'description' => 'Ciment pour maçonnerie.',
            'unit' => 'sac',
            'weight_kg' => 50,
            'length_cm' => 60,
            'width_cm' => 40,
            'height_cm' => 20,
            'fragile' => false,
            'requires_unloading' => false,
        ]);
        $master->id = 25;

        $shop = new Shop();
        $shop->id = 12;

        $attributes = $method->invoke(
            $service,
            7,
            $shop,
            $master,
            [
                'price' => 5000,
                'stock' => 100,
                'min_order_quantity' => 1,
                'availability_status' => 'in_stock',
            ],
            'actif',
            true,
            null
        );

        $this->assertSame(12, $attributes['shop_id']);
        $this->assertSame(25, $attributes['master_product_id']);
        $this->assertSame(0.048, $attributes['volume_m3']);
        $this->assertSame('actif', $attributes['status']);
        $this->assertTrue($attributes['is_active']);
        $this->assertNotEmpty($attributes['slug']);
    }
}
