<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_delivery_zones', function (Blueprint $table) {
            if (!Schema::hasColumn('product_delivery_zones', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('estimated_delay');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_delivery_zones', function (Blueprint $table) {
            if (Schema::hasColumn('product_delivery_zones', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
