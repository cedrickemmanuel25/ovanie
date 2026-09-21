<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        // Sur SQLite, ne pas supprimer vendor_id : si une FK existe encore dans
        // la définition de table, DROP COLUMN provoque :
        // unknown column "vendor_id" in foreign key definition.
        // On ajoute seulement shop_id pour que les tests puissent migrer.
        if (DB::getDriverName() === 'sqlite') {
            if (!Schema::hasColumn('orders', 'shop_id')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->unsignedBigInteger('shop_id')->nullable();
                });
            }

            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'vendor_id')) {
                $table->dropForeign(['vendor_id']);
                $table->dropColumn('vendor_id');
            }

            if (!Schema::hasColumn('orders', 'shop_id')) {
                $table->unsignedBigInteger('shop_id')->nullable()->after('product_id');
                $table->foreign('shop_id')
                    ->references('id')
                    ->on('shops')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            if (Schema::hasColumn('orders', 'shop_id')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->dropColumn('shop_id');
                });
            }

            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shop_id')) {
                $table->dropForeign(['shop_id']);
                $table->dropColumn('shop_id');
            }

            if (!Schema::hasColumn('orders', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('product_id');
                $table->foreign('vendor_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }
};
