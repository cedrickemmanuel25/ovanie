<?php

namespace Tests\Unit;

use App\Services\LogisticsTerritoryCoverageService;
use App\Services\OvanieReferenceDataService;
use Tests\TestCase;

class OvanieReferenceDataServiceTest extends TestCase
{
    private function service(): OvanieReferenceDataService
    {
        return new OvanieReferenceDataService(new LogisticsTerritoryCoverageService());
    }

    public function test_product_units_come_from_official_contract(): void
    {
        $units = $this->service()->codes('product_units');

        $this->assertContains('sac', $units);
        $this->assertContains('piece', $units);
        $this->assertContains('m3', $units);
        $this->assertCount(15, $units);
    }

    public function test_address_types_are_centralized(): void
    {
        $options = $this->service()->options('address_types');

        $this->assertSame(
            ['home', 'office', 'site', 'warehouse', 'other'],
            array_column($options, 'code')
        );
    }

    public function test_checkout_operators_reuse_mobile_money_contract(): void
    {
        $operators = $this->service()->checkoutOperators();

        $this->assertSame(
            ['wave', 'orange', 'mtn', 'moov', 'card'],
            array_column($operators, 'code')
        );
    }

    public function test_static_snapshot_exposes_contract_and_registry_versions(): void
    {
        $snapshot = $this->service()->staticSnapshot();

        $this->assertSame('1.0.0', $snapshot['contract_version']);
        $this->assertSame('1.0.0', $snapshot['registry_version']);
        $this->assertArrayHasKey('product_states', $snapshot['references']);
        $this->assertArrayHasKey('vehicle_types', $snapshot['references']);
    }
}
