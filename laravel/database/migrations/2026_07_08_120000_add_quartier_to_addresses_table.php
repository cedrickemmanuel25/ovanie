<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('addresses') && ! Schema::hasColumn('addresses', 'quartier')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('quartier', 150)->nullable()->after('commune');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('addresses') && Schema::hasColumn('addresses', 'quartier')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('quartier');
            });
        }
    }
};
