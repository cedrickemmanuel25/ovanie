<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product::$fillable/$casts et VendorProductController::payBoost() utilisent
     * déjà boost_package/boost_priority/boost_duration_days, mais aucune des
     * migrations boost précédentes ne créait ces 3 colonnes : tout paiement de
     * boost échouait avec une erreur SQL "no such column: boost_package" au
     * moment d'enregistrer le pack choisi sur le produit.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'boost_package')) {
                $table->string('boost_package')->nullable()->after('boost_type');
            }
            if (! Schema::hasColumn('products', 'boost_duration_days')) {
                $table->unsignedInteger('boost_duration_days')->nullable()->after('boost_package');
            }
            if (! Schema::hasColumn('products', 'boost_priority')) {
                $table->unsignedInteger('boost_priority')->nullable()->after('boost_duration_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('products', 'boost_package')) $cols[] = 'boost_package';
            if (Schema::hasColumn('products', 'boost_duration_days')) $cols[] = 'boost_duration_days';
            if (Schema::hasColumn('products', 'boost_priority')) $cols[] = 'boost_priority';

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
