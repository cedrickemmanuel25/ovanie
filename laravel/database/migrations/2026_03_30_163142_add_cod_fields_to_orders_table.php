<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            if (!Schema::hasColumn('orders', 'commission_amount')) {
                $table->integer('commission_amount')->nullable();
            }

            if (!Schema::hasColumn('orders', 'cod_mobile_number')) {
                $table->string('cod_mobile_number')->nullable();
            }

            if (!Schema::hasColumn('orders', 'remaining_amount')) {
                $table->integer('remaining_amount')->nullable();
            }

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            if (Schema::hasColumn('orders', 'commission_amount')) {
                $table->dropColumn('commission_amount');
            }

            if (Schema::hasColumn('orders', 'cod_mobile_number')) {
                $table->dropColumn('cod_mobile_number');
            }

            if (Schema::hasColumn('orders', 'remaining_amount')) {
                $table->dropColumn('remaining_amount');
            }

        });
    }
};
