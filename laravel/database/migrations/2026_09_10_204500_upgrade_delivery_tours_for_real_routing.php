<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_tours')) {
            Schema::table('delivery_tours', function (Blueprint $table) {
                if (Schema::hasColumn('delivery_tours', 'distance_km')) {
                    $table->decimal('distance_km', 10, 2)->nullable()->change();
                }
                if (! Schema::hasColumn('delivery_tours', 'fleet_vehicle_id')) {
                    $table->unsignedBigInteger('fleet_vehicle_id')->nullable()->index()->after('driver_id');
                }
                if (! Schema::hasColumn('delivery_tours', 'vehicle_capacity_kg')) {
                    $table->decimal('vehicle_capacity_kg', 10, 2)->nullable()->after('fleet_vehicle_id');
                }
                if (! Schema::hasColumn('delivery_tours', 'vehicle_volume_m3')) {
                    $table->decimal('vehicle_volume_m3', 10, 3)->nullable()->after('vehicle_capacity_kg');
                }
                if (! Schema::hasColumn('delivery_tours', 'routing_provider')) {
                    $table->string('routing_provider', 80)->nullable()->after('duration_minutes');
                }
                if (! Schema::hasColumn('delivery_tours', 'route_is_complete')) {
                    $table->boolean('route_is_complete')->default(false)->after('routing_provider');
                }
                if (! Schema::hasColumn('delivery_tours', 'optimized_at')) {
                    $table->timestamp('optimized_at')->nullable()->after('route_is_complete');
                }
                if (! Schema::hasColumn('delivery_tours', 'start_label')) {
                    $table->string('start_label')->nullable()->after('optimized_at');
                }
                if (! Schema::hasColumn('delivery_tours', 'start_latitude')) {
                    $table->decimal('start_latitude', 10, 7)->nullable()->after('start_label');
                }
                if (! Schema::hasColumn('delivery_tours', 'start_longitude')) {
                    $table->decimal('start_longitude', 11, 7)->nullable()->after('start_latitude');
                }
            });
        }

        if (Schema::hasTable('delivery_tour_stops')) {
            Schema::table('delivery_tour_stops', function (Blueprint $table) {
                if (! Schema::hasColumn('delivery_tour_stops', 'stop_key')) {
                    $table->string('stop_key', 180)->nullable()->index()->after('sequence');
                }
                if (! Schema::hasColumn('delivery_tour_stops', 'stop_type')) {
                    $table->string('stop_type', 30)->default('delivery')->index()->after('stop_key');
                }
                if (! Schema::hasColumn('delivery_tour_stops', 'label')) {
                    $table->string('label')->nullable()->after('stop_type');
                }
                if (! Schema::hasColumn('delivery_tour_stops', 'address')) {
                    $table->text('address')->nullable()->after('label');
                }
                if (! Schema::hasColumn('delivery_tour_stops', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('address');
                }
                if (! Schema::hasColumn('delivery_tour_stops', 'longitude')) {
                    $table->decimal('longitude', 11, 7)->nullable()->after('latitude');
                }
                if (! Schema::hasColumn('delivery_tour_stops', 'distance_from_previous_km')) {
                    $table->decimal('distance_from_previous_km', 10, 2)->nullable()->after('longitude');
                }
                if (! Schema::hasColumn('delivery_tour_stops', 'duration_from_previous_minutes')) {
                    $table->unsignedInteger('duration_from_previous_minutes')->nullable()->after('distance_from_previous_km');
                }
                if (! Schema::hasColumn('delivery_tour_stops', 'estimated_arrival_at')) {
                    $table->timestamp('estimated_arrival_at')->nullable()->after('duration_from_previous_minutes');
                }
                if (! Schema::hasColumn('delivery_tour_stops', 'route_segment_geometry')) {
                    $table->longText('route_segment_geometry')->nullable()->after('estimated_arrival_at');
                }
                if (! Schema::hasColumn('delivery_tour_stops', 'routing_provider')) {
                    $table->string('routing_provider', 80)->nullable()->after('route_segment_geometry');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_tour_stops')) {
            $columns = collect([
                'stop_key', 'stop_type', 'label', 'address', 'latitude', 'longitude',
                'distance_from_previous_km', 'duration_from_previous_minutes',
                'estimated_arrival_at', 'route_segment_geometry', 'routing_provider',
            ])->filter(fn ($column) => Schema::hasColumn('delivery_tour_stops', $column))->all();
            if ($columns !== []) {
                Schema::table('delivery_tour_stops', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        if (Schema::hasTable('delivery_tours')) {
            $columns = collect([
                'fleet_vehicle_id', 'vehicle_capacity_kg', 'vehicle_volume_m3', 'routing_provider', 'route_is_complete', 'optimized_at',
                'start_label', 'start_latitude', 'start_longitude',
            ])->filter(fn ($column) => Schema::hasColumn('delivery_tours', $column))->all();
            if ($columns !== []) {
                Schema::table('delivery_tours', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }
    }
};
