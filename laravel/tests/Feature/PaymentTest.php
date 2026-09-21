<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    public function test_payment_api_routes_are_protected(): void
    {
        foreach ([
            ['GET', 'api/payments'],
            ['POST', 'api/payments'],
            ['GET', 'api/payments/{payment}'],
        ] as [$method, $uri]) {
            $route = $this->findRoute($method, $uri);
            $this->assertNotNull($route, "Route {$method} {$uri} introuvable.");
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "Route {$method} {$uri} doit être protégée.");
        }
    }

    public function test_wave_webhook_remains_public_but_throttled(): void
    {
        $route = $this->findRoute('POST', 'api/wave/webhook');
        $this->assertNotNull($route, 'Webhook Wave introuvable.');
        $this->assertNotContains('auth:sanctum', $route->gatherMiddleware(), 'Le webhook Wave doit rester public pour le provider.');
        $this->assertTrue(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'throttle')), 'Le webhook Wave doit être rate-limité.');
    }

    public function test_payment_proof_download_is_authenticated_and_private(): void
    {
        $route = $this->findRoute('GET', 'api/payment-proofs/{paymentProof}/file');

        $this->assertNotNull($route);
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());

        $controller = file_get_contents(app_path('Http/Controllers/Api/PaymentProofController.php'));
        $this->assertStringContainsString("store('payment_proofs', 'local')", $controller);
        $this->assertStringNotContainsString("store('payment_proofs', 'public')", $controller);
    }

    private function findRoute(string $method, string $uri): mixed
    {
        return collect(Route::getRoutes())->first(fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $uri);
    }
}
