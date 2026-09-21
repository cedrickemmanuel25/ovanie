<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {

            if (!Schema::hasColumn('order_items', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            }

        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {

            if (Schema::hasColumn('order_items', 'shop_id')) {
                $table->dropColumn('shop_id');
            }

        });
    }
};
