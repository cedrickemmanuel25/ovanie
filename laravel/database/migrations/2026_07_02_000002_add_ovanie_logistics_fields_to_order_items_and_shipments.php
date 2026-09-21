<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->table('order_items', [
            'vendor_visible_at' => fn (Blueprint $table) => $table->timestamp('vendor_visible_at')->nullable()->index(),
            'logistics_vehicle_code' => fn (Blueprint $table) => $table->string('logistics_vehicle_code')->nullable()->index(),
            'logistics_vehicle_label' => fn (Blueprint $table) => $table->string('logistics_vehicle_label')->nullable(),
            'logistics_weight_kg' => fn (Blueprint $table) => $table->decimal('logistics_weight_kg', 12, 3)->nullable(),
            'logistics_volume_m3' => fn (Blueprint $table) => $table->decimal('logistics_volume_m3', 12, 4)->nullable(),
        ]);

        $this->table('shipments', [
            'routing_provider' => fn (Blueprint $table) => $table->string('routing_provider')->nullable()->index(),
            'traffic_delay_minutes' => fn (Blueprint $table) => $table->unsignedInteger('traffic_delay_minutes')->nullable(),
            'no_traffic_duration_minutes' => fn (Blueprint $table) => $table->unsignedInteger('no_traffic_duration_minutes')->nullable(),
            'routed_at' => fn (Blueprint $table) => $table->timestamp('routed_at')->nullable()->index(),
            'vehicle_code' => fn (Blueprint $table) => $table->string('vehicle_code')->nullable()->index(),
            'vehicle_label' => fn (Blueprint $table) => $table->string('vehicle_label')->nullable(),
            'total_weight_kg' => fn (Blueprint $table) => $table->decimal('total_weight_kg', 12, 3)->nullable(),
            'total_volume_m3' => fn (Blueprint $table) => $table->decimal('total_volume_m3', 12, 4)->nullable(),
            'internal_carrier_type' => fn (Blueprint $table) => $table->string('internal_carrier_type')->nullable()->index(),
            'internal_carrier_name' => fn (Blueprint $table) => $table->string('internal_carrier_name')->nullable(),
            'waze_url' => fn (Blueprint $table) => $table->text('waze_url')->nullable(),
        ]);
    }

    public function down(): void
    {
        $this->dropColumns('shipments', [
            'routing_provider',
            'traffic_delay_minutes',
            'no_traffic_duration_minutes',
            'routed_at',
            'vehicle_code',
            'vehicle_label',
            'total_weight_kg',
            'total_volume_m3',
            'internal_carrier_type',
            'internal_carrier_name',
            'waze_url',
        ]);

        $this->dropColumns('order_items', [
            'vendor_visible_at',
            'logistics_vehicle_code',
            'logistics_vehicle_label',
            'logistics_weight_kg',
            'logistics_volume_m3',
        ]);
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

    private function dropColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $existing = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($table, $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($existing));
    }
};
