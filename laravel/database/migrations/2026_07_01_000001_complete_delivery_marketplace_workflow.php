<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seller_delivery_profiles')) {
            Schema::create('seller_delivery_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->boolean('is_enabled')->default(false);
                $table->string('default_delay')->nullable();
                $table->decimal('max_weight_kg', 12, 3)->nullable();
                $table->decimal('max_volume_m3', 12, 4)->nullable();
                $table->json('vehicle_types')->nullable();
                $table->string('capacity_description')->nullable();
                $table->text('conditions')->nullable();
                $table->string('status', 50)->default('incomplete');
                $table->timestamp('validated_at')->nullable();
                $table->unsignedBigInteger('validated_by')->nullable()->index();
                $table->timestamps();
                $table->unique('shop_id');
            });
        }

        if (! Schema::hasTable('seller_delivery_zones')) {
            Schema::create('seller_delivery_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->foreignId('seller_delivery_profile_id')->nullable()->constrained('seller_delivery_profiles')->cascadeOnDelete();
                $table->string('city')->nullable();
                $table->string('commune')->nullable();
                $table->string('district')->nullable();
                $table->decimal('delivery_price', 12, 2)->default(0);
                $table->string('estimated_delay')->nullable();
                $table->decimal('max_weight_kg', 12, 3)->nullable();
                $table->decimal('max_volume_m3', 12, 4)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['shop_id', 'commune', 'district'], 'sdz_shop_zone_idx');
            });
        }

        if (! Schema::hasTable('seller_delivery_capacities')) {
            Schema::create('seller_delivery_capacities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->foreignId('seller_delivery_profile_id')->nullable()->constrained('seller_delivery_profiles')->cascadeOnDelete();
                $table->string('vehicle_type');
                $table->decimal('max_weight_kg', 12, 3)->nullable();
                $table->decimal('max_volume_m3', 12, 4)->nullable();
                $table->unsignedInteger('max_orders_per_day')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('delivery_incidents')) {
            Schema::create('delivery_incidents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->unsignedBigInteger('shipment_id')->nullable()->index();
                $table->string('reported_by_type')->nullable();
                $table->unsignedBigInteger('reported_by_id')->nullable()->index();
                $table->string('incident_type')->nullable();
                $table->string('responsibility')->nullable();
                $table->text('description')->nullable();
                $table->string('photo_path')->nullable();
                $table->string('status')->default('open');
                $table->text('resolution_note')->nullable();
                $table->timestamp('rescheduled_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $this->decimalColumn($table, 'delivery_fee_total');
                $this->decimalColumn($table, 'ovanie_delivery_fee');
                $this->decimalColumn($table, 'seller_delivery_fee');
                $this->decimalColumn($table, 'partner_delivery_fee');
                $this->stringColumn($table, 'delivery_pricing_status', 50, 'unknown');
                $this->jsonColumn($table, 'delivery_pricing_meta');
                $this->timestampColumn($table, 'seller_ready_for_pickup_at');
                $this->timestampColumn($table, 'driver_assigned_at');
                $this->timestampColumn($table, 'picked_up_at');
                $this->timestampColumn($table, 'in_transit_at');
                $this->timestampColumn($table, 'delivered_at');
                $this->decimalColumn($table, 'platform_commission');
                $this->decimalColumn($table, 'vendor_payout_amount');
            });
        }

        if (Schema::hasTable('driver_locations')) {
            Schema::table('driver_locations', function (Blueprint $table) {
                $this->unsignedBigIntegerColumn($table, 'order_id');
            });
        } else {
            Schema::create('driver_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_id')->nullable()->index();
                $table->unsignedBigInteger('shipment_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy', 10, 2)->nullable();
                $table->decimal('speed', 10, 2)->nullable();
                $table->decimal('heading', 10, 2)->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Non destructive: delivery workflow data must be preserved.
    }

    private function stringColumn(Blueprint $table, string $name, int $length = 255, ?string $default = null): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $column = $table->string($name, $length)->nullable();
            if ($default !== null) {
                $column->default($default);
            }
        }
    }

    private function decimalColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->decimal($name, 12, 2)->default(0);
        }
    }

    private function jsonColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->json($name)->nullable();
        }
    }

    private function timestampColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->timestamp($name)->nullable();
        }
    }

    private function unsignedBigIntegerColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->unsignedBigInteger($name)->nullable()->index();
        }
    }
};
