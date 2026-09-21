<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'sale_type')) {
            return;
        }

        $now = now();

        if (Schema::hasColumn('products', 'flash_end')) {
            DB::table('products')
                ->whereRaw('LOWER(sale_type) = ?', ['vente flash'])
                ->whereNull('flash_end')
                ->update([
                    'flash_end' => $now->copy()->addHours(
                        max(1, (int) config('homepage.promotion_windows.flash_default_hours', 24))
                    ),
                ]);
        }

        if (Schema::hasColumn('products', 'bf_start') && Schema::hasColumn('products', 'bf_end')) {
            $ids = DB::table('products')
                ->whereRaw('LOWER(sale_type) = ?', ['black friday'])
                ->where(function ($query) {
                    $query->whereNull('bf_start')->orWhereNull('bf_end');
                })
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('products')
                    ->whereIn('id', $ids)
                    ->update([
                        'bf_start' => DB::raw('COALESCE(bf_start, CURRENT_TIMESTAMP)'),
                        'bf_end' => $now->copy()->addDays(
                            max(1, (int) config('homepage.promotion_windows.black_friday_default_days', 7))
                        ),
                    ]);
            }
        }

    }

    public function down(): void
    {
        // Migration de réparation des données : aucun retour arrière destructif.
    }
};
