<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gains des livreurs OVANIE : une ligne par mission livrée, versée juste après
 * la remise au client (net = prix de livraison − commission OVANIE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('delivery_drivers')->cascadeOnDelete();
            $table->string('mission_number', 60);
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('reference', 60)->unique();
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            // Paiement à la livraison : espèces remises par le client au livreur.
            $table->decimal('cash_collected', 12, 2)->default(0);
            // Part des espèces à reverser à OVANIE (espèces − net), soldée par la Logistique.
            $table->decimal('cash_due', 12, 2)->default(0);
            $table->timestamp('cash_settled_at')->nullable();
            // Montant réellement viré au livreur (net − espèces conservées).
            $table->decimal('transfer_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->string('operator', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'mission_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payouts');
    }
};
