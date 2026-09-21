<?php

namespace Tests\Unit;

use App\Services\CommissionService;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    public function test_it_calculates_percentage_commission(): void
    {
        $service = new CommissionService();

        $this->assertSame(5000.0, $service->calculate(100000, null, 0.05));
        $this->assertSame(7500.0, $service->calculate(100000, null, 0.075));
    }

    public function test_default_rate_is_available(): void
    {
        $service = new CommissionService();

        $this->assertGreaterThan(0, $service->rateForShop());
    }

    public function test_it_extracts_commission_already_included_in_public_price(): void
    {
        $service = new CommissionService();

        $this->assertSame(105000.0, $service->publicAmountFromSellerAmount(100000, null, 0.05));
        $this->assertSame(5000.0, $service->commissionFromPublicAmount(105000, null, 0.05));
        $this->assertSame(100000.0, $service->sellerAmountFromPublicAmount(105000, null, 0.05));
    }
}
