<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_assignments', 'price_amount')) {
                $table->decimal('price_amount', 12, 2)->nullable()->after('meta');
            }
            if (! Schema::hasColumn('delivery_assignments', 'driver_commission_percent')) {
                $table->decimal('driver_commission_percent', 5, 2)->nullable()->after('price_amount');
            }
            if (! Schema::hasColumn('delivery_assignments', 'driver_net_amount')) {
                $table->decimal('driver_net_amount', 12, 2)->nullable()->after('driver_commission_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            foreach (['price_amount', 'driver_commission_percent', 'driver_net_amount'] as $column) {
                if (Schema::hasColumn('delivery_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
