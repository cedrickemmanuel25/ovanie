<?php

namespace Tests\Feature;

use App\Models\VendorPaymentVerificationRequest;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CommerceWorkflowIntegrationTest extends TestCase
{
    public function test_payment_webhook_and_admin_payout_routes_are_registered(): void
    {
        foreach ([
            'paydunya.webhook',
            'admin.payouts.approve',
            'admin.payouts.processing',
            'admin.payouts.markPaid',
            'admin.payouts.failed',
            'admin.payment-verifications.index',
            'admin.payment-verifications.approve',
            'admin.payment-verifications.reject',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Route {$name} introuvable.");
        }
    }

    public function test_paydunya_webhook_remains_public_but_throttled(): void
    {
        $route = Route::getRoutes()->getByName('paydunya.webhook');

        $this->assertNotNull($route, 'Webhook PayDunya introuvable.');
        $this->assertNotContains('auth', $route->gatherMiddleware());
        $this->assertTrue(
            collect($route->gatherMiddleware())->contains(fn ($middleware) => str_starts_with((string) $middleware, 'throttle')),
            'Le webhook PayDunya doit etre rate-limite.'
        );
    }

    public function test_vendor_payment_verification_model_and_payment_service_are_connected(): void
    {
        $this->assertTrue(class_exists(VendorPaymentVerificationRequest::class));

        foreach ([
            'markAsPaid',
            'holdEscrow',
            'releaseToVendor',
            'markAsRefunded',
            'shouldCreateVendorPayout',
        ] as $method) {
            $this->assertTrue(method_exists(PaymentService::class, $method), "PaymentService::{$method} est manquant.");
        }
    }
}
