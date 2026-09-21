<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('logistics_territory_zones')) {
            return;
        }

        Schema::create('logistics_territory_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('region')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('communes_count')->default(0);
            $table->json('covered_communes')->nullable();
            $table->unsignedSmallInteger('average_delay_hours')->default(24)->index();
            $table->unsignedInteger('activity_7d')->default(0);
            $table->decimal('activity_growth_percent', 6, 2)->default(0);
            $table->unsignedTinyInteger('current_load_percent')->default(0);
            $table->unsignedSmallInteger('drivers_available')->default(0);
            $table->unsignedSmallInteger('drivers_total')->default(0);
            $table->string('responsible_name')->nullable();
            $table->string('coverage_start_time', 5)->default('06:00');
            $table->string('coverage_end_time', 5)->default('22:00');
            $table->text('operational_note')->nullable();
            $table->decimal('average_distance_km', 8, 2)->default(0);
            $table->string('zone_type', 60)->default('Urbaine');
            $table->string('delivery_density', 60)->default('Moyenne');
            $table->unsignedTinyInteger('sla_percent')->default(0);
            $table->boolean('allow_express')->default(true);
            $table->boolean('prioritize_missions')->default(true);
            $table->boolean('auto_apply_new_missions')->default(true);
            $table->boolean('show_in_filters')->default(true);
            $table->json('drivers_snapshot')->nullable();
            $table->json('activity_snapshot')->nullable();
            $table->string('map_variant', 50)->default('abidjan');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['region', 'is_active', 'average_delay_hours'], 'territory_region_status_delay_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_territory_zones');
    }
};
