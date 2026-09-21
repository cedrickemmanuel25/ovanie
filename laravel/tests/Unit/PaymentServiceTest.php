<?php

namespace Tests\Unit;

use App\Services\PaymentService;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    public function test_supported_payment_methods_include_ovanie_providers(): void
    {
        $service = new PaymentService();

        foreach ([
            'wave',
            'orange_money',
            'mtn_money',
            'moov_money',
            'paydunya',
            'flutterwave',
            'cash_on_delivery',
            'manual_proof',
        ] as $method) {
            $this->assertContains($method, $service->supportedMethods());
        }
    }

    public function test_payment_statuses_include_escrow_lifecycle(): void
    {
        $service = new PaymentService();

        foreach ([
            'pending',
            'paid',
            'failed',
            'cancelled',
            'refunded',
            'escrow_held',
            'released_to_vendor',
        ] as $status) {
            $this->assertContains($status, $service->statuses());
        }
    }

    public function test_it_exposes_professional_payment_lifecycle_actions(): void
    {
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

    public function test_it_normalizes_common_payment_aliases(): void
    {
        $service = new PaymentService();

        $this->assertSame('orange_money', $service->ensureSupportedMethod('Orange Money'));
        $this->assertSame('cash_on_delivery', $service->ensureSupportedMethod('cod'));
    }

    public function test_it_rejects_unknown_payment_method(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PaymentService())->ensureSupportedMethod('crypto_unknown');
    }
}
