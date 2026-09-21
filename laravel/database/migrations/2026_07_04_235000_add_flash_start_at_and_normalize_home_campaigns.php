<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (! Schema::hasColumn('products', 'flash_start_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->timestamp('flash_start_at')->nullable()->after('is_negotiable');
                $table->index(['sale_type', 'flash_start_at', 'flash_end'], 'products_flash_window_idx');
            });
        }

        if (Schema::hasColumn('products', 'sale_type')) {
            DB::table('products')
                ->whereIn(DB::raw('LOWER(sale_type)'), ['flash', 'flash sale', 'flash_sale', 'vente_flash'])
                ->update(['sale_type' => 'vente flash']);

            DB::table('products')
                ->whereIn(DB::raw('LOWER(sale_type)'), ['black_friday'])
                ->update(['sale_type' => 'black friday']);

            DB::table('products')
                ->whereIn(DB::raw('LOWER(sale_type)'), ['promo'])
                ->update(['sale_type' => 'promotion']);
        }

        if (Schema::hasColumn('products', 'flash_start_at')) {
            DB::table('products')
                ->where('sale_type', 'vente flash')
                ->whereNull('flash_start_at')
                ->update([
                    'flash_start_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
                ]);
        }

        // Les anciennes fenêtres Black Friday créées automatiquement pouvaient
        // empêcher la récurrence hebdomadaire. Une fenêtre déjà expirée est
        // convertie en inscription récurrente : NULL = visible chaque vendredi.
        if (Schema::hasColumn('products', 'bf_end') && Schema::hasColumn('products', 'bf_start')) {
            DB::table('products')
                ->where('sale_type', 'black friday')
                ->whereNotNull('bf_end')
                ->where('bf_end', '<', now())
                ->update([
                    'bf_start' => null,
                    'bf_end' => null,
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'flash_start_at')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropIndex('products_flash_window_idx');
            } catch (Throwable) {
                // L'index peut ne pas exister sur certaines installations anciennes.
            }

            $table->dropColumn('flash_start_at');
        });
    }
};
