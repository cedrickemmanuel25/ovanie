<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LogisticsQuoteTest extends TestCase
{
    public function test_logistics_quote_route_exists_and_is_protected(): void
    {
        $route = $this->findRoute('POST', 'api/logistics/quotes');
        $this->assertNotNull($route, 'Route POST /api/logistics/quotes introuvable.');
        $this->assertContains('auth:sanctum', $route->gatherMiddleware(), 'Le calcul de devis livraison doit être protégé par auth:sanctum.');
    }

    private function findRoute(string $method, string $uri): mixed
    {
        return collect(Route::getRoutes())->first(fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $uri);
    }
}
