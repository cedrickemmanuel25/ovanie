<?php

namespace Tests\Feature;

use App\Models\DeliveryVehicleRateCard;
use App\Models\LogisticsPricingSupplement;
use App\Services\LogisticsVehicleResolver;
use App\Services\OvanieDeliveryPriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsPricingEditorsTest extends TestCase
{
    use RefreshDatabase;

    private function motoRate(array $overrides = []): DeliveryVehicleRateCard
    {
        return DeliveryVehicleRateCard::query()->updateOrCreate(['vehicle_code' => 'moto'], array_merge([
            'vehicle_label' => 'Moto',
            'min_weight_kg' => 0,
            'max_weight_kg' => 20,
            'max_volume_m3' => 0.18,
            'base_fee' => 500,
            'price_per_km' => 200,
            'price_per_kg' => 100,
            'price_per_m3' => 50,
            'is_active' => true,
            'meta' => [],
        ], $overrides));
    }

    private function price(float $weight, float $volume, float $km, array $destination = []): array
    {
        return app(OvanieDeliveryPriceCalculator::class)->calculateMetrics(
            $weight,
            $volume,
            ['distance_km' => $km],
            $destination,
            null,
            null,
            'moto'
        );
    }

    public function test_price_is_base_plus_distance_weight_and_volume(): void
    {
        $this->motoRate();

        // 500 + 5 km × 200 + 2 kg × 100 + 0,02 m³ × 50 = 1 701
        $result = $this->price(2, 0.02, 5);

        $this->assertSame(1701.0, $result['price']);
        $this->assertSame('vehicle_rate_card', $result['rate_source']);
        $this->assertEquals(500.0, $result['components']['base_fee']);
        $this->assertEquals(1000.0, $result['components']['distance_fee']);
        $this->assertEquals(200.0, $result['components']['weight_fee']);
        $this->assertEquals(1.0, $result['components']['volume_fee']);
    }

    public function test_formula_is_strict_without_minimum_or_maximum(): void
    {
        $this->motoRate(['min_fee' => 5000, 'max_fee' => 800, 'margin_type' => 'percentage', 'margin_value' => 50]);

        $this->assertSame(1701.0, $this->price(2, 0.02, 5)['price']);
    }

    public function test_active_supplement_is_added_as_an_optional_fee(): void
    {
        $this->motoRate();
        LogisticsPricingSupplement::query()->create([
            'code' => 'fragile',
            'label' => 'Produit fragile',
            'category' => 'Fragile',
            'calculation_type' => 'fixed',
            'amount' => 300,
            'scope' => 'Par livraison',
            'conditions' => ['fragile' => true],
            'minimum_threshold' => 0,
            'compatible_with_others' => true,
            'automatic' => true,
            'is_active' => true,
            'meta' => ['created_from' => 'pricing_supplements_page'],
        ]);

        $this->assertSame(1701.0, $this->price(2, 0.02, 5)['price']);
        $withFee = $this->price(2, 0.02, 5, ['fragile' => true]);
        $this->assertSame(2001.0, $withFee['price']);
        $this->assertEquals(300.0, $withFee['components']['supplement_fragile']);
    }

    public function test_vehicle_without_rates_cannot_price_a_delivery(): void
    {
        $result = $this->price(2, 0.02, 5);

        $this->assertSame(0.0, $result['price']);
        $this->assertSame('missing_vehicle_rate', $result['rate_source']);
    }

    public function test_default_vehicle_capacities_are_available_without_configuration(): void
    {
        $vehicle = app(LogisticsVehicleResolver::class)->resolve(15, 0.05);

        $this->assertSame('moto', $vehicle['code']);
        $this->assertTrue($vehicle['is_active']);
    }
}
