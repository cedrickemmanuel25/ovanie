<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductTest extends TestCase
{
    public function test_public_product_routes_are_read_only(): void
    {
        $this->assertRouteExists('GET', 'api/products');
        $this->assertRouteExists('GET', 'api/products/{product}');
        $this->assertRouteDoesNotUseMiddleware('GET', 'api/products', 'auth:sanctum');
    }

    public function test_product_write_routes_are_protected_by_sanctum(): void
    {
        $this->assertRouteUsesMiddleware('POST', 'api/products', 'auth:sanctum');
        $this->assertRouteUsesMiddleware('PATCH', 'api/products/{product}', 'auth:sanctum');
        $this->assertRouteUsesMiddleware('DELETE', 'api/products/{product}', 'auth:sanctum');
    }

    private function assertRouteExists(string $method, string $uri): void
    {
        $this->assertNotNull($this->findRoute($method, $uri), "Route {$method} {$uri} introuvable.");
    }

    private function assertRouteUsesMiddleware(string $method, string $uri, string $middleware): void
    {
        $route = $this->findRoute($method, $uri);
        $this->assertNotNull($route, "Route {$method} {$uri} introuvable.");
        $this->assertContains($middleware, $route->gatherMiddleware(), "Route {$method} {$uri} doit utiliser {$middleware}.");
    }

    private function assertRouteDoesNotUseMiddleware(string $method, string $uri, string $middleware): void
    {
        $route = $this->findRoute($method, $uri);
        $this->assertNotNull($route, "Route {$method} {$uri} introuvable.");
        $this->assertNotContains($middleware, $route->gatherMiddleware(), "Route {$method} {$uri} doit rester publique en lecture.");
    }

    private function findRoute(string $method, string $uri): mixed
    {
        return collect(Route::getRoutes())->first(fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $uri);
    }
}
