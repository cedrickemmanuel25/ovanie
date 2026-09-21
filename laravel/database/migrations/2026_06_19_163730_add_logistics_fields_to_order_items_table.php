<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'vendor_delivery_status')) {
                $table->string('vendor_delivery_status')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'vendor_delivery_updated_at')) {
                $table->timestamp('vendor_delivery_updated_at')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'vendor_delivery_note')) {
                $table->text('vendor_delivery_note')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'driver_name')) {
                $table->string('driver_name')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'driver_phone')) {
                $table->string('driver_phone')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'vehicle_plate')) {
                $table->string('vehicle_plate')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'driver_latitude')) {
                $table->decimal('driver_latitude', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('order_items', 'driver_longitude')) {
                $table->decimal('driver_longitude', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('order_items', 'driver_location_updated_at')) {
                $table->timestamp('driver_location_updated_at')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'pickup_photo')) {
                $table->string('pickup_photo')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'delivery_photo')) {
                $table->string('delivery_photo')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'delivery_otp_code')) {
                $table->string('delivery_otp_code')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'delivery_otp_verified_at')) {
                $table->timestamp('delivery_otp_verified_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $cols = [];
            foreach ([
                'vendor_delivery_status',
                'vendor_delivery_updated_at',
                'vendor_delivery_note',
                'driver_name',
                'driver_phone',
                'vehicle_plate',
                'driver_latitude',
                'driver_longitude',
                'driver_location_updated_at',
                'pickup_photo',
                'delivery_photo',
                'delivery_otp_code',
                'delivery_otp_verified_at'
            ] as $col) {
                if (Schema::hasColumn('order_items', $col)) $cols[] = $col;
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
