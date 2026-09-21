<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasColumn('payments', 'operator')) {
                    $table->string('operator', 50)->nullable();
                }

                if (! Schema::hasColumn('payments', 'mobile_number')) {
                    $table->string('mobile_number', 30)->nullable();
                }

                if (! Schema::hasColumn('payments', 'provider_payload')) {
                    $table->json('provider_payload')->nullable();
                }

                if (! Schema::hasColumn('payments', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable();
                }

                if (! Schema::hasColumn('payments', 'failed_at')) {
                    $table->timestamp('failed_at')->nullable();
                }

                if (! Schema::hasColumn('payments', 'released_at')) {
                    $table->timestamp('released_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                try {
                    $table->index('order_id', 'payments_order_id_idx');
                } catch (Throwable $e) {}

                try {
                    $table->index('status', 'payments_status_idx');
                } catch (Throwable $e) {}

                try {
                    $table->index('method', 'payments_method_idx');
                } catch (Throwable $e) {}

                try {
                    $table->index('reference', 'payments_reference_idx');
                } catch (Throwable $e) {}
            });
        }

        if (Schema::hasTable('commissions')) {
            Schema::table('commissions', function (Blueprint $table) {
                try {
                    $table->index('order_id', 'commissions_order_id_idx');
                } catch (Throwable $e) {}

                try {
                    $table->index('shop_id', 'commissions_shop_id_idx');
                } catch (Throwable $e) {}

                try {
                    $table->index('status', 'commissions_status_idx');
                } catch (Throwable $e) {}
            });
        }

        if (Schema::hasTable('vendor_payouts')) {
            Schema::table('vendor_payouts', function (Blueprint $table) {
                try {
                    $table->index('order_id', 'vendor_payouts_order_id_idx');
                } catch (Throwable $e) {}

                try {
                    $table->index('shop_id', 'vendor_payouts_shop_id_idx');
                } catch (Throwable $e) {}

                try {
                    $table->index('vendor_id', 'vendor_payouts_vendor_id_idx');
                } catch (Throwable $e) {}

                try {
                    $table->index('status', 'vendor_payouts_status_idx');
                } catch (Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                foreach (['operator', 'mobile_number', 'provider_payload', 'paid_at', 'failed_at', 'released_at'] as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
