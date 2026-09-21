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
    Schema::table('products', function (Blueprint $table) {

        if (!Schema::hasColumn('products', 'vendor_id')) {
            $table->unsignedBigInteger('vendor_id')->after('id');
            $table->foreign('vendor_id')->references('id')->on('users')->onDelete('cascade');
        }

        if (!Schema::hasColumn('products', 'category')) {
            $table->string('category')->nullable()->after('vendor_id');
        }

        if (!Schema::hasColumn('products', 'sale_type')) {
            $table->string('sale_type')->nullable()->after('category');
        }
    });
}

public function down(): void
{
    Schema::table('products', function (Blueprint $table) {
        if (Schema::hasColumn('products', 'sale_type')) {
            $table->dropColumn('sale_type');
        }
        if (Schema::hasColumn('products', 'category')) {
            $table->dropColumn('category');
        }
        if (Schema::hasColumn('products', 'vendor_id')) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');
        }
    });
}


};
