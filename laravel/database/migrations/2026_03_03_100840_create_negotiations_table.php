<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negotiations', function (Blueprint $table) {
            $table->id();

            // Produit concerné
            $table->foreignId('product_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Boutique concernée
            $table->foreignId('shop_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Acheteur (client)
            $table->foreignId('buyer_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Prix proposé
            $table->decimal('proposed_price', 15, 2);

            // Statut
            $table->enum('status', ['pending', 'accepted', 'rejected'])
                  ->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiations');
    }
};
