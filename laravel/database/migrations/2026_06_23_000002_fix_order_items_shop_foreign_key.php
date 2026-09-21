<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_items') || !Schema::hasTable('shops') || !Schema::hasColumn('order_items', 'shop_id')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            try {
                $table->dropForeign(['shop_id']);
            } catch (Throwable $exception) {
                // The foreign key may already be absent on partially fixed databases.
            }
        });

        $this->remapLegacyUserIdsToShopIds();

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('shop_id')
                ->references('id')
                ->on('shops')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('order_items') || !Schema::hasColumn('order_items', 'shop_id')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            try {
                $table->dropForeign(['shop_id']);
            } catch (Throwable $exception) {
                // Keep rollback tolerant for local databases with manual fixes.
            }
        });

        if (Schema::hasTable('users')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('shop_id')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            });
        }
    }

    private function remapLegacyUserIdsToShopIds(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE order_items oi
                JOIN shops s ON s.user_id = oi.shop_id
                LEFT JOIN shops existing_shop ON existing_shop.id = oi.shop_id
                SET oi.shop_id = s.id
                WHERE oi.shop_id IS NOT NULL
                  AND existing_shop.id IS NULL
            ");

            return;
        }

        DB::table('order_items')
            ->whereNotNull('shop_id')
            ->orderBy('id')
            ->each(function ($orderItem) {
                $alreadyShop = DB::table('shops')->where('id', $orderItem->shop_id)->exists();

                if ($alreadyShop) {
                    return;
                }

                $shop = DB::table('shops')->where('user_id', $orderItem->shop_id)->first();

                if ($shop) {
                    DB::table('order_items')
                        ->where('id', $orderItem->id)
                        ->update(['shop_id' => $shop->id]);
                }
            });
    }
};
