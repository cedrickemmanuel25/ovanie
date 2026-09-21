<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_breakdown')) {
                $table->json('delivery_breakdown')->nullable()->after('delivery_fee');
            }

            if (!Schema::hasColumn('orders', 'selected_carriers')) {
                $table->json('selected_carriers')->nullable()->after('delivery_breakdown');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('orders', 'selected_carriers')) {
                $columns[] = 'selected_carriers';
            }

            if (Schema::hasColumn('orders', 'delivery_breakdown')) {
                $columns[] = 'delivery_breakdown';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
