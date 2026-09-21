<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StaffWorkspaceArchitectureTest extends TestCase
{
    public function test_logistics_layout_uses_the_unified_internal_guard(): void
    {
        Auth::guard('admin')->setUser(new User(['name' => 'Zx Manager']));

        $html = view('layouts.logistics')->render();

        $this->assertStringContainsString('ZX', $html);
    }

    public function test_staff_create_form_renders_its_permission_configuration(): void
    {
        $html = view('admin.staff._form', [
            'managers' => collect(),
        ])->render();

        $this->assertStringContainsString('name="permissions_present"', $html);
        $this->assertStringContainsString('data-permission-role="support"', $html);
        $this->assertStringNotContainsString('@php', $html);
    }

    public function test_internal_login_is_separate_from_customer_login(): void
    {
        $customerLogin = Route::getRoutes()->getByName('login');
        $internalLogin = Route::getRoutes()->getByName('admin.adminlogin');

        $this->assertNotNull($customerLogin);
        $this->assertNotNull($internalLogin);
        $this->assertSame('login', $customerLogin->uri());
        $this->assertSame('administration/login', $internalLogin->uri());
    }

    public function test_support_routes_are_internal_guarded_and_role_protected(): void
    {
        foreach (['support.dashboard', 'support.tickets.index', 'support.tickets.store', 'support.messages.index'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} absente.");
            $middleware = collect($route->gatherMiddleware());
            $this->assertTrue($middleware->contains('auth:admin'));
            $this->assertTrue($middleware->contains('internal'));
            $this->assertTrue($middleware->contains('staff:support'));
        }
    }

    public function test_commercial_routes_are_internal_guarded_and_role_protected(): void
    {
        foreach (['commercial.dashboard', 'commercial.leads.index', 'commercial.leads.store', 'commercial.quotes.index'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} absente.");
            $middleware = collect($route->gatherMiddleware());
            $this->assertTrue($middleware->contains('auth:admin'));
            $this->assertTrue($middleware->contains('internal'));
            $this->assertTrue($middleware->contains('staff:commercial'));
        }
    }

    public function test_logistics_routes_use_the_unified_internal_guard(): void
    {
        foreach (['logistics.dashboard', 'logistics.shipments', 'logistics.tracking', 'logistics.drivers'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} absente.");
            $middleware = collect($route->gatherMiddleware());
            $this->assertTrue($middleware->contains('auth:admin'));
            $this->assertTrue($middleware->contains('internal'));
            $this->assertTrue($middleware->contains('staff:logistique'));
            $this->assertFalse($middleware->contains('auth:logistics'));
        }
    }

    public function test_staff_api_routes_are_sanctum_and_role_protected(): void
    {
        $expectations = [
            'api.staff.support.tickets.index' => 'staff:support',
            'api.staff.commercial.leads.index' => 'staff:commercial',
        ];

        foreach ($expectations as $name => $roleMiddleware) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route API {$name} absente.");
            $middleware = collect($route->gatherMiddleware());
            $this->assertTrue($middleware->contains('auth:sanctum'));
            $this->assertTrue($middleware->contains($roleMiddleware));
        }
    }

    public function test_admin_staff_management_is_strictly_admin_guarded(): void
    {
        foreach ([
            'admin.staff.index', 'admin.staff.create', 'admin.staff.store',
            'admin.staff.edit', 'admin.staff.update', 'admin.staff.status',
            'admin.staff.password', 'admin.staff.destroy', 'admin.staff.login-logs',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route administration {$name} absente.");
            $middleware = collect($route->gatherMiddleware());
            $this->assertTrue($middleware->contains('auth:admin'));
            $this->assertTrue($middleware->contains('internal'));
            $this->assertTrue($middleware->contains('isAdmin'));
        }
    }

    public function test_every_staff_business_route_has_a_permission_mapping_or_is_a_dashboard(): void
    {
        $patterns = array_keys(config('staff.route_permissions', []));

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'support.')
                && ! str_starts_with($name, 'commercial.')
                && ! str_starts_with($name, 'logistics.')) {
                continue;
            }

            if (in_array($name, ['support.dashboard', 'commercial.dashboard', 'logistics.legacy-login'], true)) {
                continue;
            }

            $mapped = collect($patterns)->contains(
                fn (string $pattern) => \Illuminate\Support\Str::is($pattern, $name)
            );
            $this->assertTrue($mapped, "Aucune permission configurée pour {$name}.");
        }
    }
}
