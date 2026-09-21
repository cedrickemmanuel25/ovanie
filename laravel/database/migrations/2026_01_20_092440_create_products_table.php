<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Clés étrangères
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            // Infos produit
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Prix & stock
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);

            // Type de produit (ex: 'vente flash', 'black friday', etc.)
            $table->string('type')->nullable();

            // Images stockées en JSON (tableau d’URLs ou chemins)
            $table->json('images')->nullable();

            // Champs pour la négociation de prix
            $table->decimal('price_p1', 10, 2)->nullable(); // Prix plafond
            $table->decimal('price_p2', 10, 2)->nullable(); // Prix contre-offre
            $table->decimal('price_p3', 10, 2)->nullable(); // Prix plancher

            // Dates pour ventes flash et Black Friday
            $table->timestamp('flash_end')->nullable();
            $table->timestamp('bf_start')->nullable();
            $table->timestamp('bf_end')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
