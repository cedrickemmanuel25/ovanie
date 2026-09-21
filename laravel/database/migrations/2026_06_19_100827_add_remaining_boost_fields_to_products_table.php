<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'boost_views')) {
                $table->unsignedBigInteger('boost_views')->default(0)->after('boost_payment_status');
            }
            if (!Schema::hasColumn('products', 'boost_clicks')) {
                $table->unsignedBigInteger('boost_clicks')->default(0)->after('boost_views');
            }
            if (!Schema::hasColumn('products', 'boost_type')) {
                $table->string('boost_type')->nullable()->after('boost_clicks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('products', 'boost_views')) $cols[] = 'boost_views';
            if (Schema::hasColumn('products', 'boost_clicks')) $cols[] = 'boost_clicks';
            if (Schema::hasColumn('products', 'boost_type')) $cols[] = 'boost_type';
            
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
