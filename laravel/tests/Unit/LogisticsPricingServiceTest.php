<?php

namespace Tests\Unit;

use App\Services\LogisticsPricingService;
use Tests\TestCase;

class LogisticsPricingServiceTest extends TestCase
{
    public function test_it_calculates_delivery_price_with_surcharges(): void
    {
        $service = new LogisticsPricingService();

        $result = $service->calculate([
            'base_price' => 5000,
            'price_per_km' => 250,
            'price_per_ton' => 1500,
            'zone_surcharge' => 1000,
            'urgency_surcharge' => 2000,
            'fragile_surcharge' => 500,
            'unloading_surcharge' => 1000,
        ], [
            'distance_km' => 20,
            'weight_ton' => 2,
            'is_urgent' => true,
            'is_fragile' => true,
            'requires_unloading' => true,
        ]);

        $this->assertSame(17500.0, $result['final_price']);
        $this->assertSame(5000.0, $result['distance_amount']);
        $this->assertSame(3000.0, $result['weight_amount']);
    }
}
