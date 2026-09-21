<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (!Schema::hasColumn('shops', 'payment_mode')) {
                // 'post_delivery' = Paiement après livraison (72h, commission 5%)
                // 'weekly'        = Paiement hebdomadaire (100% gratuit)
                $table->string('payment_mode', 30)->default('weekly')->after('direct_payment');
            }
        });

        // Mettre à jour les boutiques existantes avec une valeur par défaut réelle
        DB::table('shops')->whereNull('payment_mode')->update(['payment_mode' => 'weekly']);
        // Les boutiques qui avaient direct_payment = 1 gardent 'post_delivery'
        DB::table('shops')->where('direct_payment', 1)->update(['payment_mode' => 'post_delivery']);
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });
    }
};
