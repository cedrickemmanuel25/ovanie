<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'supply_delay')) {
                $table->string('supply_delay', 100)->nullable()->after('packaging');
            }

            if (! Schema::hasColumn('products', 'unloading_instructions')) {
                $table->string('unloading_instructions')->nullable()->after('requires_unloading');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'unloading_instructions')) {
                $table->dropColumn('unloading_instructions');
            }

            if (Schema::hasColumn('products', 'supply_delay')) {
                $table->dropColumn('supply_delay');
            }
        });
    }
};
