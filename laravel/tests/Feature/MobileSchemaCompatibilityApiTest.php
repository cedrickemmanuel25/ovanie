<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileSchemaCompatibilityApiTest extends TestCase
{
    public function test_current_client_schema_is_compatible(): void
    {
        $this->getJson('/api/mobile/v1/reference-data/compatibility?app=client&schema_version=1.0.0')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('compatible', true)
            ->assertJsonPath('compatibility.server_schema_version', '1.0.0');
    }

    public function test_old_schema_is_rejected(): void
    {
        $this->getJson('/api/mobile/v1/reference-data/compatibility?app=vendor&schema_version=0.9.0')
            ->assertOk()
            ->assertJsonPath('compatible', false);
    }

    public function test_unknown_app_is_rejected(): void
    {
        $this->getJson('/api/mobile/v1/reference-data/compatibility?app=unknown&schema_version=1.0.0')
            ->assertStatus(422)
            ->assertJsonPath('compatible', false);
    }
}
