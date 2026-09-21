<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_vehicle_rate_cards')) {
            Schema::create('delivery_vehicle_rate_cards', function (Blueprint $table) {
                $table->id();
                $table->string('vehicle_code')->index();
                $table->string('vehicle_label');
                $table->decimal('min_weight_kg', 12, 3)->nullable();
                $table->decimal('max_weight_kg', 12, 3)->nullable();
                $table->decimal('max_volume_m3', 12, 4)->nullable();
                $table->decimal('base_fee', 12, 2)->default(0);
                $table->decimal('price_per_km', 12, 2)->default(0);
                $table->decimal('price_per_kg', 12, 4)->default(0);
                $table->decimal('price_per_m3', 12, 2)->default(0);
                $table->decimal('handling_fee', 12, 2)->default(0);
                $table->decimal('unloading_fee', 12, 2)->default(0);
                $table->decimal('fragile_fee', 12, 2)->default(0);
                $table->decimal('urgent_fee', 12, 2)->default(0);
                $table->decimal('traffic_surcharge_fee', 12, 2)->default(0);
                $table->decimal('intra_commune_min_fee', 12, 2)->default(0);
                $table->decimal('min_fee', 12, 2)->nullable();
                $table->decimal('max_fee', 12, 2)->nullable();
                $table->string('margin_type', 30)->default('none');
                $table->decimal('margin_value', 12, 2)->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique('vehicle_code');
                $table->index(['is_active', 'min_weight_kg', 'max_weight_kg'], 'dvrc_active_weight_idx');
            });
        }

        if (! Schema::hasTable('delivery_commune_rate_rules')) {
            Schema::create('delivery_commune_rate_rules', function (Blueprint $table) {
                $table->id();
                $table->string('origin_commune')->nullable()->index();
                $table->string('destination_commune')->nullable()->index();
                $table->enum('relation_type', [
                    'same_commune',
                    'neighboring_commune',
                    'distant_commune',
                    'inter_city',
                    'unknown',
                ])->default('unknown')->index();
                $table->decimal('relation_fee', 12, 2)->default(0);
                $table->decimal('min_fee', 12, 2)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['origin_commune', 'destination_commune', 'relation_type'], 'dcrr_communes_type_idx');
            });
        }

        if (! Schema::hasTable('delivery_destination_surcharges')) {
            Schema::create('delivery_destination_surcharges', function (Blueprint $table) {
                $table->id();
                $table->string('commune')->nullable()->index();
                $table->string('city')->nullable()->index();
                $table->string('delivery_zone')->nullable()->index();
                $table->decimal('surcharge_fee', 12, 2)->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['delivery_zone', 'city', 'commune'], 'dds_zone_city_commune_idx');
            });
        }

        if (! Schema::hasTable('delivery_route_cache')) {
            Schema::create('delivery_route_cache', function (Blueprint $table) {
                $table->id();
                $table->string('provider')->index();
                $table->decimal('origin_lat', 10, 7);
                $table->decimal('origin_lng', 10, 7);
                $table->decimal('destination_lat', 10, 7);
                $table->decimal('destination_lng', 10, 7);
                $table->decimal('distance_km', 10, 2)->nullable();
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->unsignedInteger('traffic_delay_minutes')->nullable();
                $table->unsignedInteger('no_traffic_duration_minutes')->nullable();
                $table->longText('route_geometry')->nullable();
                $table->json('raw_response')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();

                $table->index([
                    'provider',
                    'origin_lat',
                    'origin_lng',
                    'destination_lat',
                    'destination_lng',
                ], 'drc_provider_coordinates_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_route_cache');
        Schema::dropIfExists('delivery_destination_surcharges');
        Schema::dropIfExists('delivery_commune_rate_rules');
        Schema::dropIfExists('delivery_vehicle_rate_cards');
    }
};
