<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    public function test_checkout_web_routes_exist(): void
    {
        $this->assertNotNull($this->findRoute('GET', 'checkout'), 'Route GET /checkout introuvable.');
        $this->assertNotNull($this->findRoute('POST', 'checkout'), 'Route POST /checkout introuvable.');
    }

    public function test_checkout_store_route_is_protected_for_authenticated_buyers(): void
    {
        $route = $this->findRoute('POST', 'checkout');
        $this->assertNotNull($route, 'Route POST /checkout introuvable.');
        $this->assertTrue(
            collect($route->gatherMiddleware())->contains(fn ($middleware) => str_contains((string) $middleware, 'auth')),
            'La validation checkout doit être protégée par un middleware auth.'
        );
    }

    public function test_checkout_delivery_preview_route_exists_when_available(): void
    {
        $route = $this->findRoute('POST', 'checkout/delivery-fee-preview');
        $this->assertNotNull($route, 'Route POST /checkout/delivery-fee-preview introuvable pour calcul livraison.');
    }

    private function findRoute(string $method, string $uri): mixed
    {
        return collect(Route::getRoutes())->first(fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $uri);
    }
}
