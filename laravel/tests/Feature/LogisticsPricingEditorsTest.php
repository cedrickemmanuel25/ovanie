<?php

namespace Tests\Feature;

use App\Models\DeliveryVehicleRateCard;
use App\Models\LogisticsPricingMatrix;
use App\Services\CommuneRelationResolver;
use App\Services\LogisticsVehicleResolver;
use App\Services\OvanieDeliveryPriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsPricingEditorsTest extends TestCase
{
    use RefreshDatabase;

    private function motoRate(): void
    {
        DeliveryVehicleRateCard::query()->updateOrCreate(['vehicle_code' => 'moto'], [
            'vehicle_label' => 'Moto',
            'min_weight_kg' => 0,
            'max_weight_kg' => 20,
            'max_volume_m3' => 0.18,
            'base_fee' => 500,
            'price_per_km' => 200,
            'price_per_kg' => 100,
            'price_per_m3' => 50,
            'fragile_fee' => 0,
            'handling_fee' => 0,
            'unloading_fee' => 0,
            'urgent_fee' => 0,
            'traffic_surcharge_fee' => 0,
            'min_fee' => 500,
            'margin_type' => 'none',
            'margin_value' => 0,
            'is_active' => true,
            'meta' => [],
        ]);
    }

    public function test_commune_matrix_is_the_primary_price_for_a_vehicle(): void
    {
        $this->motoRate();
        LogisticsPricingMatrix::query()->create([
            'region' => 'Abidjan',
            'origin_commune' => 'Cocody',
            'destination_commune' => 'Adjamé',
            'vehicle_prices' => ['moto' => 2500],
            'is_active' => true,
        ]);

        $result = app(OvanieDeliveryPriceCalculator::class)->calculateMetrics(
            2,
            0.02,
            ['distance_km' => 5],
            ['origin_commune' => 'Cocody', 'destination_commune' => 'Adjamé'],
            null,
            null,
            'moto'
        );

        $this->assertSame(2500.0, $result['price']);
        $this->assertSame('logistics_pricing_matrix', $result['rate_source']);
        $this->assertSame(2500.0, (float) $result['components']['commune_route_fee']);
    }

    public function test_same_commune_tariff_is_supported(): void
    {
        $this->motoRate();
        LogisticsPricingMatrix::query()->create([
            'region' => 'Abidjan',
            'origin_commune' => 'Cocody',
            'destination_commune' => 'Cocody',
            'vehicle_prices' => ['moto' => 1500],
            'is_active' => true,
        ]);

        $result = app(OvanieDeliveryPriceCalculator::class)->calculateMetrics(
            3,
            0.01,
            [],
            ['origin_commune' => 'Cocody', 'destination_commune' => 'Cocody'],
            null,
            null,
            'moto'
        );

        $this->assertSame(1500.0, $result['price']);
        $this->assertSame('logistics_pricing_matrix', $result['rate_source']);
    }

    public function test_missing_commune_tariff_does_not_fall_back_to_distance_formula(): void
    {
        $this->motoRate();

        $result = app(OvanieDeliveryPriceCalculator::class)->calculateMetrics(
            2,
            0.02,
            ['distance_km' => 5],
            ['origin_commune' => 'Cocody', 'destination_commune' => 'Abobo'],
            null,
            null,
            'moto'
        );

        $this->assertSame(0.0, $result['price']);
        $this->assertSame('missing_commune_tariff', $result['rate_source']);
    }

    public function test_exact_direction_has_priority_over_reverse_fallback(): void
    {
        LogisticsPricingMatrix::query()->create([
            'region' => 'Abidjan', 'origin_commune' => 'Cocody', 'destination_commune' => 'Adjamé',
            'vehicle_prices' => ['moto' => 2500], 'is_active' => true,
        ]);
        LogisticsPricingMatrix::query()->create([
            'region' => 'Abidjan', 'origin_commune' => 'Adjamé', 'destination_commune' => 'Cocody',
            'vehicle_prices' => ['moto' => 3000], 'is_active' => true,
        ]);

        $relation = app(CommuneRelationResolver::class)->resolve('Adjamé', 'Cocody', 'Abidjan', 'Cocody', 'moto');
        $this->assertSame(3000.0, (float) $relation['fixed_tariff']);
    }
    public function test_commune_matrix_works_without_vehicle_price_configuration(): void
    {
        LogisticsPricingMatrix::query()->create([
            'region' => 'Abidjan',
            'origin_commune' => 'Cocody',
            'destination_commune' => 'Abobo',
            'vehicle_prices' => ['moto' => 2200],
            'is_active' => true,
        ]);

        $result = app(OvanieDeliveryPriceCalculator::class)->calculateMetrics(
            5,
            0.03,
            [],
            ['origin_commune' => 'Cocody', 'destination_commune' => 'Abobo'],
            null,
            null,
            'moto'
        );

        $this->assertSame(2200.0, $result['price']);
        $this->assertSame('logistics_pricing_matrix', $result['rate_source']);
        $this->assertNull($result['rate_card_id']);
    }

    public function test_default_vehicle_capacities_are_available_without_configuration(): void
    {
        $vehicle = app(LogisticsVehicleResolver::class)->resolve(15, 0.05);

        $this->assertSame('moto', $vehicle['code']);
        $this->assertTrue($vehicle['is_active']);
        $this->assertNull($vehicle['rate_card']);
    }

    public function test_reverse_direction_is_not_used_implicitly(): void
    {
        LogisticsPricingMatrix::query()->create([
            'region' => 'Abidjan',
            'origin_commune' => 'Cocody',
            'destination_commune' => 'Adjamé',
            'vehicle_prices' => ['moto' => 2500],
            'is_active' => true,
        ]);

        $relation = app(CommuneRelationResolver::class)->resolve('Adjamé', 'Cocody', 'Abidjan', 'Cocody', 'moto');

        $this->assertNull($relation['fixed_tariff']);
        $this->assertSame('dynamic', $relation['pricing_mode']);
    }

}
