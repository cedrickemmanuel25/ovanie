<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cart_items')) {
            return;
        }

        if (! Schema::hasColumn('cart_items', 'price_source')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->string('price_source', 20)->default('catalog')->after('price')->index();
            });
        }

        if (! Schema::hasColumn('cart_items', 'negotiation_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->unsignedBigInteger('negotiation_id')->nullable()->after('price_source')->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cart_items')) {
            return;
        }

        if (Schema::hasColumn('cart_items', 'negotiation_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropColumn('negotiation_id');
            });
        }

        if (Schema::hasColumn('cart_items', 'price_source')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropColumn('price_source');
            });
        }
    }
};
