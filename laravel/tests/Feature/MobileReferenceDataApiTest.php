<?php

namespace Tests\Feature;

use App\Services\OvanieReferenceDataService;
use Tests\TestCase;

class MobileReferenceDataApiTest extends TestCase
{
    public function test_reference_data_endpoint_is_public_and_returns_unified_payload(): void
    {
        $this->mock(OvanieReferenceDataService::class, function ($mock): void {
            $mock->shouldReceive('apiPayload')->once()->andReturn([
                'ok' => true,
                'meta' => [
                    'service' => 'OVANIE Reference Data',
                    'endpoint_version' => 'v1',
                    'schema_version' => '1.0.0',
                    'registry_version' => '1.0.0',
                    'source' => 'laravel',
                    'read_only' => true,
                ],
                'data' => [
                    'product_states' => [
                        ['code' => 'new', 'label' => 'Neuf'],
                    ],
                    'categories' => [],
                    'communes' => [],
                ],
            ]);
        });

        $response = $this->getJson('/api/mobile/v1/reference-data');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('meta.service', 'OVANIE Reference Data')
            ->assertJsonPath('meta.schema_version', '1.0.0')
            ->assertJsonPath('data.product_states.0.code', 'new');
    }

    public function test_reference_data_meta_endpoint_is_public(): void
    {
        $this->mock(OvanieReferenceDataService::class, function ($mock): void {
            $mock->shouldReceive('apiMeta')->once()->andReturn([
                'service' => 'OVANIE Reference Data',
                'endpoint_version' => 'v1',
                'schema_version' => '1.0.0',
                'registry_version' => '1.0.0',
                'source' => 'laravel',
                'read_only' => true,
                'available_keys' => ['product_states', 'categories', 'communes'],
            ]);
        });

        $response = $this->getJson('/api/mobile/v1/reference-data/meta');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('meta.endpoint_version', 'v1')
            ->assertJsonPath('meta.read_only', true)
            ->assertJsonFragment(['product_states']);
    }
}
