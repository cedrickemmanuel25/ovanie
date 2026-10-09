<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('commercial_capture_sessions')) {
            return;
        }

        Schema::table('commercial_capture_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('commercial_capture_sessions', 'product_name')) {
                $table->string('product_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('commercial_capture_sessions', 'product_price')) {
                $table->decimal('product_price', 15, 2)->nullable()->after('product_name');
            }
            if (! Schema::hasColumn('commercial_capture_sessions', 'product_promo_price')) {
                $table->decimal('product_promo_price', 15, 2)->nullable()->after('product_price');
            }
            if (! Schema::hasColumn('commercial_capture_sessions', 'product_stock')) {
                $table->integer('product_stock')->nullable()->after('product_promo_price');
            }
            if (! Schema::hasColumn('commercial_capture_sessions', 'product_weight_kg')) {
                $table->decimal('product_weight_kg', 12, 3)->nullable()->after('product_stock');
            }
            if (! Schema::hasColumn('commercial_capture_sessions', 'product_unit')) {
                $table->string('product_unit', 40)->nullable()->after('product_weight_kg');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commercial_capture_sessions')) {
            return;
        }

        Schema::table('commercial_capture_sessions', function (Blueprint $table) {
            foreach ([
                'product_name',
                'product_price',
                'product_promo_price',
                'product_stock',
                'product_weight_kg',
                'product_unit',
            ] as $column) {
                if (Schema::hasColumn('commercial_capture_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
