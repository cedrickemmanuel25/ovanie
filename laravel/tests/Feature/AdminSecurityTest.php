<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    public function test_admin_dashboard_routes_are_protected_by_admin_auth(): void
    {
        foreach ([
            ['GET', 'admin/dashboard'],
            ['GET', 'admin/dashboard/data'],
        ] as [$method, $uri]) {
            $route = $this->findRoute($method, $uri);
            $this->assertNotNull($route, "Route {$method} {$uri} introuvable.");
            $this->assertContains('auth:admin', $route->gatherMiddleware(), "Route {$method} {$uri} doit utiliser auth:admin.");
        }
    }

    public function test_admin_api_routes_are_not_public(): void
    {
        foreach ([
            ['GET', 'api/admin/users'],
            ['POST', 'api/admin/users'],
            ['GET', 'api/admin/commissions'],
        ] as [$method, $uri]) {
            $route = $this->findRoute($method, $uri);
            $this->assertNotNull($route, "Route {$method} {$uri} introuvable.");
            $middleware = collect($route->gatherMiddleware());
            $this->assertContains('auth:sanctum', $middleware, "Route {$method} {$uri} doit utiliser auth:sanctum.");
            $this->assertTrue(
                $middleware->contains(fn ($m) => in_array((string) $m, ['isAdmin', 'can:admin'], true)),
                "Route {$method} {$uri} doit utiliser isAdmin ou can:admin."
            );
        }
    }

    public function test_admin_self_registration_is_disabled_by_default(): void
    {
        config()->set('security.admin_registration_enabled', false);

        $this->get('/admin/register')->assertNotFound();
        $this->post('/admin/register/gate', ['password' => 'irrelevant'])->assertNotFound();
    }

    public function test_public_installation_scripts_are_absent(): void
    {
        $this->assertFileDoesNotExist(public_path('run_setup.php'));
        $this->assertFileDoesNotExist(public_path('rename_logos.php'));
    }

    private function findRoute(string $method, string $uri): mixed
    {
        return collect(Route::getRoutes())->first(fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $uri);
    }
}
