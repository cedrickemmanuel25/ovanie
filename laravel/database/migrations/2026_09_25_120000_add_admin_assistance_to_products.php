<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'created_by_admin_id')) {
                $table->unsignedBigInteger('created_by_admin_id')->nullable()->after('created_by_commercial_id')->index();
            }
            if (! Schema::hasColumn('products', 'updated_by_admin_id')) {
                $table->unsignedBigInteger('updated_by_admin_id')->nullable()->after('created_by_admin_id')->index();
            }
            if (! Schema::hasColumn('products', 'created_on_behalf_of_vendor')) {
                $table->boolean('created_on_behalf_of_vendor')->default(false)->after('updated_by_admin_id')->index();
            }
            if (! Schema::hasColumn('products', 'admin_last_image_update_at')) {
                $table->timestamp('admin_last_image_update_at')->nullable()->after('created_on_behalf_of_vendor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['admin_last_image_update_at','created_on_behalf_of_vendor','updated_by_admin_id','created_by_admin_id'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
