<?php
namespace Tests\Unit;

use App\Models\Shop;
use App\Services\VendorFinanceService;
use App\Services\VendorPayoutScheduleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class VendorFinanceMonthlySummaryTest extends TestCase
{
    public function test_monthly_figures_are_scoped_and_do_not_use_lifetime_totals(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->travelTo(\Carbon\Carbon::parse('2026-09-09 12:00:00'));
        Schema::create('vendor_payouts', function (Blueprint $table) {
            $table->id();
            foreach (['shop_id', 'vendor_id', 'order_id'] as $column) $table->integer($column);
            foreach (['product_amount', 'seller_delivery_amount', 'ovanie_delivery_amount', 'net_product_amount', 'total_amount', 'commission_amount', 'processing_fee_amount', 'payout_amount'] as $column) $table->decimal($column, 15, 2)->default(0);
            $table->string('status'); $table->string('payment_method')->nullable(); $table->string('payment_channel')->nullable();
            $table->timestamp('paid_at')->nullable(); $table->timestamp('scheduled_for')->nullable(); $table->timestamps();
        });
        $base = ['shop_id' => 1, 'vendor_id' => 2, 'order_id' => 10, 'total_amount' => 1000, 'commission_amount' => 50, 'payout_amount' => 950, 'status' => 'paid', 'created_at' => '2026-09-01 00:00:00', 'paid_at' => '2026-09-03 00:00:00'];
        DB::table('vendor_payouts')->insert($base);
        DB::table('vendor_payouts')->insert(array_replace($base, ['order_id' => 11, 'total_amount' => 2000, 'created_at' => '2026-08-31 23:59:59', 'paid_at' => '2026-08-31 23:59:59']));
        DB::table('vendor_payouts')->insert(array_replace($base, ['shop_id' => 99, 'total_amount' => 900000]));
        DB::table('vendor_payouts')->insert(array_replace($base, ['status' => 'cancelled', 'total_amount' => 800000]));
        DB::table('vendor_payouts')->insert(array_replace($base, ['order_id' => 12, 'status' => 'pending', 'total_amount' => 500, 'payout_amount' => 475, 'paid_at' => null, 'scheduled_for' => '2026-09-13 12:00:00', 'payment_method' => 'wave']));
        $shop = new Shop(); $shop->id = 1;
        $summary = (new VendorFinanceService(Mockery::mock(VendorPayoutScheduleService::class)))->summary($shop, 2);
        $this->assertSame(3500.0, $summary['total_sales']);
        $this->assertSame(1500.0, $summary['month_sales']);
        $this->assertSame(1, $summary['month_paid_count']);
        $this->assertCount(6, $summary['monthly_revenue']);
        $this->assertSame('2026-04', $summary['monthly_revenue'][0]['month']);
        $this->assertSame(0.0, $summary['monthly_revenue'][0]['sales']);
        $this->assertSame(2000.0, $summary['monthly_revenue'][4]['sales']);
        $this->assertSame(475.0, $summary['next_payout_amount']);
        $this->assertSame('Wave', $summary['next_payout_method']);
        $this->travelBack();
    }
}
