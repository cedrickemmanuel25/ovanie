<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (!Schema::hasColumn('carts', 'delivery_fee')) {
                $table->decimal('delivery_fee', 10, 2)->default(0)->after('user_id');
            }
            if (!Schema::hasColumn('carts', 'grand_total')) {
                $table->decimal('grand_total', 10, 2)->default(0)->after('delivery_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('carts', 'delivery_fee')) $cols[] = 'delivery_fee';
            if (Schema::hasColumn('carts', 'grand_total')) $cols[] = 'grand_total';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
