<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter commission_status à la table devis
        if (Schema::hasTable('devis') && ! Schema::hasColumn('devis', 'commission_status')) {
            Schema::table('devis', function (Blueprint $table) {
                $table->string('commission_status', 30)->default('pending')->after('message');
            });
        }

        // Ajouter commission_status à la table appel_offres
        if (Schema::hasTable('appel_offres') && ! Schema::hasColumn('appel_offres', 'commission_status')) {
            Schema::table('appel_offres', function (Blueprint $table) {
                $table->string('commission_status', 30)->default('pending')->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devis', 'commission_status')) {
            Schema::table('devis', function (Blueprint $table) {
                $table->dropColumn('commission_status');
            });
        }

        if (Schema::hasColumn('appel_offres', 'commission_status')) {
            Schema::table('appel_offres', function (Blueprint $table) {
                $table->dropColumn('commission_status');
            });
        }
    }
};
