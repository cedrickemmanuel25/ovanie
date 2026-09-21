<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->table('shops', [
            'latitude' => fn (Blueprint $table) => $table->decimal('latitude', 10, 7)->nullable(),
            'longitude' => fn (Blueprint $table) => $table->decimal('longitude', 10, 7)->nullable(),
            'geo_accuracy' => fn (Blueprint $table) => $table->decimal('geo_accuracy', 10, 2)->nullable(),
            'geo_source' => fn (Blueprint $table) => $table->string('geo_source')->nullable(),
            'geo_verified_at' => fn (Blueprint $table) => $table->timestamp('geo_verified_at')->nullable(),
        ]);

        $this->table('orders', [
            'delivery_address' => fn (Blueprint $table) => $table->text('delivery_address')->nullable(),
            'delivery_latitude' => fn (Blueprint $table) => $table->decimal('delivery_latitude', 10, 7)->nullable(),
            'delivery_longitude' => fn (Blueprint $table) => $table->decimal('delivery_longitude', 10, 7)->nullable(),
            'delivery_geo_accuracy' => fn (Blueprint $table) => $table->decimal('delivery_geo_accuracy', 10, 2)->nullable(),
            'delivery_geo_source' => fn (Blueprint $table) => $table->string('delivery_geo_source')->nullable(),
        ]);

        $this->table('shipments', [
            'order_item_id' => fn (Blueprint $table) => $table->unsignedBigInteger('order_item_id')->nullable(),
            'provider_type' => fn (Blueprint $table) => $table->string('provider_type')->default('ovanie'),
            'pickup_address' => fn (Blueprint $table) => $table->text('pickup_address')->nullable(),
            'pickup_latitude' => fn (Blueprint $table) => $table->decimal('pickup_latitude', 10, 7)->nullable(),
            'pickup_longitude' => fn (Blueprint $table) => $table->decimal('pickup_longitude', 10, 7)->nullable(),
            'delivery_address' => fn (Blueprint $table) => $table->text('delivery_address')->nullable(),
            'delivery_latitude' => fn (Blueprint $table) => $table->decimal('delivery_latitude', 10, 7)->nullable(),
            'delivery_longitude' => fn (Blueprint $table) => $table->decimal('delivery_longitude', 10, 7)->nullable(),
            'distance_km' => fn (Blueprint $table) => $table->decimal('distance_km', 10, 2)->nullable(),
            'duration_minutes' => fn (Blueprint $table) => $table->integer('duration_minutes')->nullable(),
            'route_geometry' => fn (Blueprint $table) => $table->longText('route_geometry')->nullable(),
            'optimized_route_id' => fn (Blueprint $table) => $table->unsignedBigInteger('optimized_route_id')->nullable(),
        ]);

        $this->table('delivery_drivers', [
            'latitude' => fn (Blueprint $table) => $table->decimal('latitude', 10, 7)->nullable(),
            'longitude' => fn (Blueprint $table) => $table->decimal('longitude', 10, 7)->nullable(),
            'last_seen_at' => fn (Blueprint $table) => $table->timestamp('last_seen_at')->nullable(),
            'is_online' => fn (Blueprint $table) => $table->boolean('is_online')->default(false),
        ]);

        $this->table('drivers', [
            'latitude' => fn (Blueprint $table) => $table->decimal('latitude', 10, 7)->nullable(),
            'longitude' => fn (Blueprint $table) => $table->decimal('longitude', 10, 7)->nullable(),
            'last_seen_at' => fn (Blueprint $table) => $table->timestamp('last_seen_at')->nullable(),
            'is_online' => fn (Blueprint $table) => $table->boolean('is_online')->default(false),
        ]);

        if (! Schema::hasTable('driver_locations')) {
            Schema::create('driver_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_id');
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy', 10, 2)->nullable();
                $table->decimal('speed', 10, 2)->nullable();
                $table->decimal('heading', 10, 2)->nullable();
                $table->integer('battery_level')->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('geocode_cache')) {
            Schema::create('geocode_cache', function (Blueprint $table) {
                $table->id();
                $table->string('query')->index();
                $table->string('provider');
                $table->string('country_code')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->text('display_name')->nullable();
                $table->json('raw_response')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('delivery_routes')) {
            Schema::create('delivery_routes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->string('status')->default('draft');
                $table->decimal('total_distance_km', 10, 2)->nullable();
                $table->integer('total_duration_minutes')->nullable();
                $table->longText('route_geometry')->nullable();
                $table->json('optimized_payload')->nullable();
                $table->json('optimized_response')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('delivery_route_stops')) {
            Schema::create('delivery_route_stops', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_route_id');
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->string('type');
                $table->integer('stop_order');
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->timestamp('estimated_arrival_at')->nullable();
                $table->timestamp('arrived_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_route_stops');
        Schema::dropIfExists('delivery_routes');
        Schema::dropIfExists('geocode_cache');
        Schema::dropIfExists('driver_locations');
    }

    private function table(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
            foreach ($columns as $column => $callback) {
                if (! Schema::hasColumn($table, $column)) {
                    $callback($blueprint);
                }
            }
        });
    }
};
