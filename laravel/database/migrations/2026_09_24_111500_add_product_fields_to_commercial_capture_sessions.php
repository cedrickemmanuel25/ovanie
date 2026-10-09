<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
                $table->decimal('product_price', 12, 2)->nullable()->after('product_name');
            }
            if (! Schema::hasColumn('commercial_capture_sessions', 'product_weight_kg')) {
                $table->decimal('product_weight_kg', 12, 3)->nullable()->after('product_price');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commercial_capture_sessions')) {
            return;
        }

        Schema::table('commercial_capture_sessions', function (Blueprint $table) {
            $columns = [];
            foreach (['product_name', 'product_price', 'product_weight_kg'] as $column) {
                if (Schema::hasColumn('commercial_capture_sessions', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
