<?php

namespace Tests\Unit;

use App\Models\Shop;
use App\Services\OrderWorkflowService;
use App\Services\VendorPayoutScheduleService;
use App\Services\VendorPayoutService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class VendorPayoutScheduleServiceTest extends TestCase
{
    private function service(): VendorPayoutScheduleService
    {
        return new VendorPayoutScheduleService(
            Mockery::mock(VendorPayoutService::class),
            Mockery::mock(OrderWorkflowService::class),
        );
    }

    public function test_post_delivery_mode_is_scheduled_72_hours_after_eligibility(): void
    {
        config(['vendor_payouts.post_delivery_delay_hours' => 72]);
        $shop = new Shop(['payment_mode' => Shop::PAYMENT_POST_DELIVERY]);
        $eligibleAt = Carbon::parse('2026-07-06 10:30:00');

        $scheduledFor = $this->service()->scheduledFor($shop, $eligibleAt);

        $this->assertSame('2026-07-09 10:30:00', $scheduledFor->toDateTimeString());
    }

    public function test_weekly_mode_is_scheduled_on_the_next_configured_weekly_slot(): void
    {
        config([
            'vendor_payouts.weekly_iso_day' => 5,
            'vendor_payouts.weekly_hour' => 17,
            'vendor_payouts.weekly_minute' => 0,
        ]);
        $shop = new Shop(['payment_mode' => Shop::PAYMENT_WEEKLY]);

        $scheduledFor = $this->service()->scheduledFor(
            $shop,
            Carbon::parse('2026-07-06 10:30:00')
        );

        $this->assertSame('2026-07-10 17:00:00', $scheduledFor->toDateTimeString());
    }

    public function test_weekly_mode_has_no_processing_fee(): void
    {
        $shop = new Shop(['payment_mode' => Shop::PAYMENT_WEEKLY]);

        $this->assertSame(0.0, $this->service()->processingFee($shop, 100000, now()));
    }
}
