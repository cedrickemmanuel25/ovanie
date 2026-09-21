<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'vendor_delivery_method')) {
                $table->string('vendor_delivery_method', 30)->nullable()->after('vendor_tracking_number');
            }

            if (! Schema::hasColumn('order_items', 'vendor_delivery_status')) {
                $table->string('vendor_delivery_status', 40)->default('pending')->after('vendor_delivery_method');
            }

            if (! Schema::hasColumn('order_items', 'vendor_delivery_partner_name')) {
                $table->string('vendor_delivery_partner_name')->nullable()->after('vendor_delivery_status');
            }

            if (! Schema::hasColumn('order_items', 'vendor_driver_name')) {
                $table->string('vendor_driver_name')->nullable()->after('vendor_delivery_partner_name');
            }

            if (! Schema::hasColumn('order_items', 'vendor_driver_phone')) {
                $table->string('vendor_driver_phone', 40)->nullable()->after('vendor_driver_name');
            }

            if (! Schema::hasColumn('order_items', 'vendor_vehicle_plate')) {
                $table->string('vendor_vehicle_plate', 80)->nullable()->after('vendor_driver_phone');
            }

            if (! Schema::hasColumn('order_items', 'vendor_loading_photo')) {
                $table->string('vendor_loading_photo')->nullable()->after('vendor_vehicle_plate');
            }

            if (! Schema::hasColumn('order_items', 'vendor_delivery_photo')) {
                $table->string('vendor_delivery_photo')->nullable()->after('vendor_loading_photo');
            }

            if (! Schema::hasColumn('order_items', 'vendor_scheduled_pickup_at')) {
                $table->timestamp('vendor_scheduled_pickup_at')->nullable()->after('vendor_delivery_photo');
            }

            if (! Schema::hasColumn('order_items', 'vendor_picked_up_at')) {
                $table->timestamp('vendor_picked_up_at')->nullable()->after('vendor_scheduled_pickup_at');
            }

            if (! Schema::hasColumn('order_items', 'vendor_delivered_at')) {
                $table->timestamp('vendor_delivered_at')->nullable()->after('vendor_picked_up_at');
            }

            if (! Schema::hasColumn('order_items', 'vendor_delivery_note')) {
                $table->text('vendor_delivery_note')->nullable()->after('vendor_delivered_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $columns = [
                'vendor_delivery_method',
                'vendor_delivery_status',
                'vendor_delivery_partner_name',
                'vendor_driver_name',
                'vendor_driver_phone',
                'vendor_vehicle_plate',
                'vendor_loading_photo',
                'vendor_delivery_photo',
                'vendor_scheduled_pickup_at',
                'vendor_picked_up_at',
                'vendor_delivered_at',
                'vendor_delivery_note',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
