<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CalculatorTest extends TestCase
{
    public function test_calculator_estimate_route_exists_and_can_remain_public(): void
    {
        $route = $this->findRoute('POST', 'api/calculator/estimate');
        $this->assertNotNull($route, 'Route POST /api/calculator/estimate introuvable.');
        $this->assertNotContains('auth:sanctum', $route->gatherMiddleware(), 'Le calculateur express doit pouvoir rester public.');
    }

    public function test_calculator_web_routes_exist_when_module_is_installed(): void
    {
        $this->assertNotNull($this->findRoute('GET', 'calculator'), 'Route GET /calculator introuvable.');
        $this->assertNotNull($this->findRoute('POST', 'calculator/estimate'), 'Route POST /calculator/estimate introuvable.');
    }

    private function findRoute(string $method, string $uri): mixed
    {
        return collect(Route::getRoutes())->first(fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $uri);
    }
}
