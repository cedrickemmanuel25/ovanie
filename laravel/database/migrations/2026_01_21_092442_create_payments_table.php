<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Référence à la commande (clé étrangère)
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Méthode de paiement (ex: wave, orange, mtn, moov, etc.)
            $table->string('method');

            // Identifiant de transaction, nullable si pas applicable
            $table->string('transaction_id')->nullable();

            // Montant payé
            $table->decimal('amount', 12, 2);

            // Statut du paiement (ex: pending, paid, rejected)
            $table->string('status');

            // Référence externe optionnelle (ex: référence bancaire)
            $table->string('reference')->nullable();

            // Utilisateur qui a effectué le paiement (optionnel)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
