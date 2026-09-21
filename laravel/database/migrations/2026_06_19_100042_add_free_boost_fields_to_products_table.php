<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'free_boosted_by_ovanie')) {
                $table->boolean('free_boosted_by_ovanie')->default(0)->after('status');
            }
            if (!Schema::hasColumn('products', 'free_boost_start_at')) {
                $table->timestamp('free_boost_start_at')->nullable()->after('free_boosted_by_ovanie');
            }
            if (!Schema::hasColumn('products', 'free_boost_end_at')) {
                $table->timestamp('free_boost_end_at')->nullable()->after('free_boost_start_at');
            }
            if (!Schema::hasColumn('products', 'free_boost_batch_date')) {
                $table->date('free_boost_batch_date')->nullable()->after('free_boost_end_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('products', 'free_boosted_by_ovanie')) $cols[] = 'free_boosted_by_ovanie';
            if (Schema::hasColumn('products', 'free_boost_start_at')) $cols[] = 'free_boost_start_at';
            if (Schema::hasColumn('products', 'free_boost_end_at')) $cols[] = 'free_boost_end_at';
            if (Schema::hasColumn('products', 'free_boost_batch_date')) $cols[] = 'free_boost_batch_date';
            
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
