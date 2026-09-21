<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'vendor_id')) {
            return;
        }

        // Important : SQLite ne sait pas reconstruire correctement une table
        // lorsqu'une colonne encore référencée par une FK est supprimée.
        // En environnement de test SQLite, on garde vendor_id pour laisser les
        // migrations continuer. En MySQL/MariaDB de production, on supprime bien
        // la FK puis la colonne.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('products') || Schema::hasColumn('products', 'vendor_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable()->after('id');

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('vendor_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }
};
