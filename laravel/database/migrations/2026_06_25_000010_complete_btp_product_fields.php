<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 50)->nullable()->after('sale_type');
            }

            if (! Schema::hasColumn('products', 'unit_label')) {
                $table->string('unit_label', 80)->nullable()->after('unit');
            }

            if (! Schema::hasColumn('products', 'min_order_quantity')) {
                $table->unsignedInteger('min_order_quantity')->default(1)->after('unit_label');
            }

            if (! Schema::hasColumn('products', 'packaging')) {
                $table->string('packaging')->nullable()->after('min_order_quantity');
            }

            if (! Schema::hasColumn('products', 'brand')) {
                $table->string('brand')->nullable()->after('packaging');
            }

            if (! Schema::hasColumn('products', 'origin_country')) {
                $table->string('origin_country', 100)->nullable()->after('brand');
            }

            if (! Schema::hasColumn('products', 'usage_area')) {
                $table->string('usage_area')->nullable()->after('origin_country');
            }

            if (! Schema::hasColumn('products', 'material_grade')) {
                $table->string('material_grade')->nullable()->after('usage_area');
            }

            if (! Schema::hasColumn('products', 'warranty')) {
                $table->string('warranty')->nullable()->after('material_grade');
            }

            if (! Schema::hasColumn('products', 'return_policy')) {
                $table->string('return_policy')->nullable()->after('warranty');
            }

            if (! Schema::hasColumn('products', 'technical_sheet_path')) {
                $table->string('technical_sheet_path')->nullable()->after('return_policy');
            }

            if (! Schema::hasColumn('products', 'weight_kg')) {
                $table->decimal('weight_kg', 12, 3)->nullable()->after('technical_sheet_path');
            }

            if (! Schema::hasColumn('products', 'length_cm')) {
                $table->decimal('length_cm', 12, 2)->nullable()->after('weight_kg');
            }

            if (! Schema::hasColumn('products', 'width_cm')) {
                $table->decimal('width_cm', 12, 2)->nullable()->after('length_cm');
            }

            if (! Schema::hasColumn('products', 'height_cm')) {
                $table->decimal('height_cm', 12, 2)->nullable()->after('width_cm');
            }

            if (! Schema::hasColumn('products', 'volume_m3')) {
                $table->decimal('volume_m3', 12, 4)->nullable()->after('height_cm');
            }

            if (! Schema::hasColumn('products', 'fragile')) {
                $table->boolean('fragile')->default(false)->after('volume_m3');
            }

            if (! Schema::hasColumn('products', 'requires_unloading')) {
                $table->boolean('requires_unloading')->default(false)->after('fragile');
            }

            if (! Schema::hasColumn('products', 'delivery_mode')) {
                $table->string('delivery_mode', 50)->nullable()->after('requires_unloading');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $columns = [
                'unit',
                'unit_label',
                'min_order_quantity',
                'packaging',
                'brand',
                'origin_country',
                'usage_area',
                'material_grade',
                'warranty',
                'return_policy',
                'technical_sheet_path',
                'weight_kg',
                'length_cm',
                'width_cm',
                'height_cm',
                'volume_m3',
                'fragile',
                'requires_unloading',
                'delivery_mode',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
