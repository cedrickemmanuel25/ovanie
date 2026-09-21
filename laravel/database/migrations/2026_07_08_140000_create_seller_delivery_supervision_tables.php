<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seller_delivery_tracking_sessions')) {
            Schema::create('seller_delivery_tracking_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->string('access_token', 128)->unique();
                $table->unsignedBigInteger('shop_id')->index();
                $table->unsignedBigInteger('order_id')->index();
                $table->string('status', 40)->default('active')->index();
                $table->string('driver_name', 150);
                $table->string('driver_phone', 40);
                $table->string('vehicle_plate', 80)->nullable();
                $table->timestamp('started_at')->nullable()->index();
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('last_location_at')->nullable()->index();
                $table->decimal('last_latitude', 10, 7)->nullable();
                $table->decimal('last_longitude', 10, 7)->nullable();
                $table->decimal('last_accuracy', 10, 2)->nullable();
                $table->decimal('last_speed', 10, 2)->nullable();
                $table->decimal('last_heading', 10, 2)->nullable();
                $table->decimal('remaining_distance_km', 10, 2)->nullable();
                $table->unsignedInteger('eta_minutes')->nullable();
                $table->unsignedInteger('traffic_delay_minutes')->nullable();
                $table->longText('route_geometry')->nullable();
                $table->timestamp('route_calculated_at')->nullable();
                $table->string('incident_type', 80)->nullable();
                $table->text('incident_note')->nullable();
                $table->timestamp('incident_reported_at')->nullable();
                $table->timestamps();

                $table->index(['shop_id', 'status']);
                $table->index(['order_id', 'status']);
            });
        }

        if (! Schema::hasTable('seller_driver_locations')) {
            Schema::create('seller_driver_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tracking_session_id')->index();
                $table->unsignedBigInteger('shop_id')->index();
                $table->unsignedBigInteger('order_id')->index();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy', 10, 2)->nullable();
                $table->decimal('speed', 10, 2)->nullable();
                $table->decimal('heading', 10, 2)->nullable();
                $table->unsignedTinyInteger('battery_level')->nullable();
                $table->timestamp('recorded_at')->index();
                $table->timestamps();

                $table->index(['tracking_session_id', 'recorded_at'], 'seller_loc_session_time_idx');
            });
        }

        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'seller_tracking_session_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('seller_tracking_session_id')->nullable()->index()->after('driver_location_updated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'seller_tracking_session_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('seller_tracking_session_id');
            });
        }

        Schema::dropIfExists('seller_driver_locations');
        Schema::dropIfExists('seller_delivery_tracking_sessions');
    }
};
