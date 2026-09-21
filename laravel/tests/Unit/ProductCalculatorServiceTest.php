<?php

namespace Tests\Unit;

use App\Services\ProductCalculatorService;
use Tests\TestCase;

class ProductCalculatorServiceTest extends TestCase
{
    public function test_it_estimates_cement_quantity_with_waste_margin(): void
    {
        $service = new ProductCalculatorService();

        $result = $service->estimate([
            'category' => 'ciment',
            'length' => 10,
            'width' => 5,
            'thickness' => 0.10,
            'waste_margin' => 10,
        ]);

        $this->assertSame('ciment', $result['category']);
        $this->assertArrayHasKey('quantity', $result);
        $this->assertArrayHasKey('unit', $result);
        $this->assertGreaterThan(0, $result['quantity']);
    }

    public function test_it_supports_core_btp_categories(): void
    {
        $service = new ProductCalculatorService();

        $categories = $service->categories();

        foreach (['ciment', 'sable', 'gravier', 'carrelage', 'peinture'] as $category) {
            $this->assertContains($category, $categories);
        }
    }
}
