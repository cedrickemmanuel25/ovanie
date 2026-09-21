<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'boosted_by_ovanie')) {
                $table->boolean('boosted_by_ovanie')->default(0)->nullable()->after('free_boost_batch_date');
            }
            if (!Schema::hasColumn('products', 'boost_start_at')) {
                $table->timestamp('boost_start_at')->nullable()->after('boosted_by_ovanie');
            }
            if (!Schema::hasColumn('products', 'boost_end_at')) {
                $table->timestamp('boost_end_at')->nullable()->after('boost_start_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('products', 'boosted_by_ovanie')) $cols[] = 'boosted_by_ovanie';
            if (Schema::hasColumn('products', 'boost_start_at')) $cols[] = 'boost_start_at';
            if (Schema::hasColumn('products', 'boost_end_at')) $cols[] = 'boost_end_at';
            
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
