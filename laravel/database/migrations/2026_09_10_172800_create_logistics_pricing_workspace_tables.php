<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_pricing_grids')) {
            Schema::create('logistics_pricing_grids', function (Blueprint $table) {
                $table->id();
                $table->string('name', 140)->index();
                $table->string('vehicle_code', 40)->index();
                $table->string('vehicle_label', 80);
                $table->string('region', 120)->default('Abidjan')->index();
                $table->unsignedSmallInteger('zone_count')->default(0);
                $table->text('description')->nullable();
                $table->decimal('base_fee', 12, 2)->default(0);
                $table->decimal('price_per_km', 12, 2)->default(0);
                $table->decimal('price_per_kg', 12, 2)->default(0);
                $table->decimal('price_per_m3', 12, 2)->default(0);
                $table->decimal('max_weight_kg', 12, 2)->nullable();
                $table->decimal('max_volume_m3', 12, 3)->nullable();
                $table->json('surcharges')->nullable();
                $table->json('rules')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('logistics_pricing_matrices')) {
            Schema::create('logistics_pricing_matrices', function (Blueprint $table) {
                $table->id();
                $table->string('region', 120)->default('Abidjan')->index();
                $table->string('origin_commune', 120)->index();
                $table->string('destination_commune', 120)->index();
                $table->json('vehicle_prices');
                $table->json('estimated_delays')->nullable();
                $table->json('rules')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(['region', 'origin_commune', 'destination_commune'], 'log_pricing_matrix_route_unique');
            });
        }

        if (! Schema::hasTable('logistics_pricing_supplements')) {
            Schema::create('logistics_pricing_supplements', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('label', 140)->index();
                $table->string('category', 80)->index();
                $table->string('calculation_type', 30)->default('fixed');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('scope', 80)->default('Par livraison');
                $table->string('condition_label', 220)->nullable();
                $table->text('description')->nullable();
                $table->json('conditions')->nullable();
                $table->decimal('minimum_threshold', 12, 2)->nullable();
                $table->boolean('compatible_with_others')->default(true);
                $table->boolean('automatic')->default(true);
                $table->boolean('is_active')->default(true)->index();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('logistics_pricing_special_zones')) {
            Schema::create('logistics_pricing_special_zones', function (Blueprint $table) {
                $table->id();
                $table->string('name', 180)->unique();
                $table->string('type', 80)->index();
                $table->decimal('surcharge_fee', 12, 2)->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('logistics_pricing_rules')) {
            Schema::create('logistics_pricing_rules', function (Blueprint $table) {
                $table->id();
                $table->string('group', 80)->index();
                $table->string('key', 100);
                $table->string('label', 180);
                $table->string('value', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(['group', 'key'], 'log_pricing_rule_group_key_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_pricing_rules');
        Schema::dropIfExists('logistics_pricing_special_zones');
        Schema::dropIfExists('logistics_pricing_supplements');
        Schema::dropIfExists('logistics_pricing_matrices');
        Schema::dropIfExists('logistics_pricing_grids');
    }
};
