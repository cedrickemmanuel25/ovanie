<?php

namespace Tests\Unit;

use Tests\TestCase;

class CommercialUiPermissionCompatibilityTest extends TestCase
{
    public function test_commercial_account_creation_routes_keep_legacy_write_permission_compatibility(): void
    {
        $permissions = config('staff.route_permissions');

        $this->assertSame('leads.write', $permissions['commercial.clients.create'] ?? null);
        $this->assertSame('leads.write', $permissions['commercial.clients.store'] ?? null);
        $this->assertSame('leads.write', $permissions['commercial.vendors.create'] ?? null);
        $this->assertSame('leads.write', $permissions['commercial.vendors.store'] ?? null);
    }

    public function test_read_routes_keep_their_dedicated_permissions(): void
    {
        $permissions = config('staff.route_permissions');

        $this->assertSame('customers.read', $permissions['commercial.clients.*'] ?? null);
        $this->assertSame('vendors.read', $permissions['commercial.vendors.*'] ?? null);
    }
}
