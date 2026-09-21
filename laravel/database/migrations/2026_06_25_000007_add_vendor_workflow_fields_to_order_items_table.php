<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'vendor_status')) {
                $table->string('vendor_status', 50)->default('pending')->after('subtotal');
            }

            if (! Schema::hasColumn('order_items', 'vendor_status_note')) {
                $table->text('vendor_status_note')->nullable()->after('vendor_status');
            }

            if (! Schema::hasColumn('order_items', 'vendor_status_updated_at')) {
                $table->timestamp('vendor_status_updated_at')->nullable()->after('vendor_status_note');
            }

            if (! Schema::hasColumn('order_items', 'vendor_confirmed_at')) {
                $table->timestamp('vendor_confirmed_at')->nullable()->after('vendor_status_updated_at');
            }

            if (! Schema::hasColumn('order_items', 'vendor_prepared_at')) {
                $table->timestamp('vendor_prepared_at')->nullable()->after('vendor_confirmed_at');
            }

            if (! Schema::hasColumn('order_items', 'vendor_shipped_at')) {
                $table->timestamp('vendor_shipped_at')->nullable()->after('vendor_prepared_at');
            }

            if (! Schema::hasColumn('order_items', 'vendor_delivered_at')) {
                $table->timestamp('vendor_delivered_at')->nullable()->after('vendor_shipped_at');
            }

            if (! Schema::hasColumn('order_items', 'vendor_cancelled_at')) {
                $table->timestamp('vendor_cancelled_at')->nullable()->after('vendor_delivered_at');
            }

            if (! Schema::hasColumn('order_items', 'vendor_cancel_reason')) {
                $table->text('vendor_cancel_reason')->nullable()->after('vendor_cancelled_at');
            }

            if (! Schema::hasColumn('order_items', 'vendor_carrier')) {
                $table->string('vendor_carrier')->nullable()->after('vendor_cancel_reason');
            }

            if (! Schema::hasColumn('order_items', 'vendor_tracking_number')) {
                $table->string('vendor_tracking_number')->nullable()->after('vendor_carrier');
            }

            if (! Schema::hasColumn('order_items', 'vendor_shipment_date')) {
                $table->date('vendor_shipment_date')->nullable()->after('vendor_tracking_number');
            }

            if (! Schema::hasColumn('order_items', 'vendor_delivery_status')) {
                $table->string('vendor_delivery_status', 50)->nullable()->after('vendor_shipment_date');
            }
        });

        if (Schema::hasColumn('order_items', 'vendor_status')) {
            DB::table('order_items')
                ->whereNull('vendor_status')
                ->update(['vendor_status' => 'pending']);
        }

        if (Schema::hasColumn('order_items', 'vendor_delivery_status')) {
            DB::table('order_items')
                ->whereNull('vendor_delivery_status')
                ->update(['vendor_delivery_status' => 'pending']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $columns = [
                'vendor_status',
                'vendor_status_note',
                'vendor_status_updated_at',
                'vendor_confirmed_at',
                'vendor_prepared_at',
                'vendor_shipped_at',
                'vendor_delivered_at',
                'vendor_cancelled_at',
                'vendor_cancel_reason',
                'vendor_carrier',
                'vendor_tracking_number',
                'vendor_shipment_date',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
