<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {



            // 🚀 Index pour performance marketplace
            $table->index('vendor_id');
            $table->index('shop_id');
            $table->index('category_id');
            $table->index('is_active');

        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {



            $table->dropIndex(['vendor_id']);
            $table->dropIndex(['shop_id']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['is_active']);
        });
    }
};
