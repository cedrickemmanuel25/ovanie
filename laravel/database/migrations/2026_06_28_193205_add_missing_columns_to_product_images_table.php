<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_images')) {
            return;
        }

        Schema::table('product_images', function (Blueprint $table) {
            if (!Schema::hasColumn('product_images', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0);
            }

            if (!Schema::hasColumn('product_images', 'is_main')) {
                $table->boolean('is_main')->default(false);
            }

            if (!Schema::hasColumn('product_images', 'is_primary')) {
                $table->boolean('is_primary')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_images')) {
            return;
        }

        Schema::table('product_images', function (Blueprint $table) {
            if (Schema::hasColumn('product_images', 'sort_order')) {
                $table->dropColumn('sort_order');
            }

            if (Schema::hasColumn('product_images', 'is_main')) {
                $table->dropColumn('is_main');
            }

            if (Schema::hasColumn('product_images', 'is_primary')) {
                $table->dropColumn('is_primary');
            }
        });
    }
};