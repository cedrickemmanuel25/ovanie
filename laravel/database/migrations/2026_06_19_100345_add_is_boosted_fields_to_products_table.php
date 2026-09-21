<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_boosted')) {
                $table->boolean('is_boosted')->default(0)->nullable()->after('boost_end_at');
            }
            if (!Schema::hasColumn('products', 'boost_payment_status')) {
                $table->string('boost_payment_status')->nullable()->after('is_boosted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('products', 'is_boosted')) $cols[] = 'is_boosted';
            if (Schema::hasColumn('products', 'boost_payment_status')) $cols[] = 'boost_payment_status';
            
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
