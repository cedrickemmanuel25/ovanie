<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class VendorDashboardTest extends TestCase
{
    public function test_vendor_dashboard_route_exists(): void
    {
        $route = collect(Route::getRoutes())->first(function ($route) {
            $uri = $route->uri();
            $name = (string) $route->getName();

            return in_array('GET', $route->methods(), true)
                && str_contains($uri, 'dashboard')
                && (str_contains($uri, 'vendor') || str_contains($uri, 'vendeur') || str_contains($uri, 'daniel') || str_contains($name, 'vendor') || str_contains($name, 'daniel'));
        });

        $this->assertNotNull($route, 'Route dashboard vendeur introuvable.');
    }

    public function test_vendor_dashboard_route_is_authenticated(): void
    {
        $route = collect(Route::getRoutes())->first(function ($route) {
            $uri = $route->uri();
            $name = (string) $route->getName();

            return in_array('GET', $route->methods(), true)
                && str_contains($uri, 'dashboard')
                && (str_contains($uri, 'vendor') || str_contains($uri, 'vendeur') || str_contains($uri, 'daniel') || str_contains($name, 'vendor') || str_contains($name, 'daniel'));
        });

        $this->assertNotNull($route, 'Route dashboard vendeur introuvable.');
        $this->assertTrue(
            collect($route->gatherMiddleware())->contains(fn ($middleware) => str_contains((string) $middleware, 'auth')),
            'Le dashboard vendeur doit être protégé par auth.'
        );
    }
}
