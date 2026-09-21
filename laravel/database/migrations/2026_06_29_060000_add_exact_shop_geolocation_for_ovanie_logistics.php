<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shops')) {
            return;
        }

        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('shops', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('shops', 'logistics_type')) {
                $table->string('logistics_type', 30)->default('seller');
            }
        });

        if (Schema::hasColumn('shops', 'logistics_type')) {
            DB::table('shops')
                ->whereNull('logistics_type')
                ->orWhere('logistics_type', '')
                ->update(['logistics_type' => 'seller']);
        }
    }

    public function down(): void
    {
        // Migration volontairement non destructive : on ne supprime pas les coordonnées boutiques.
    }
};
