<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compte simple OVANIE PRO : publication d'une offre ou d'un besoin.
 * Les coordonnées de l'auteur ne sont jamais stockées dans la publication :
 * elles restent sur le compte (pro_users) et ne sont pas affichées publiquement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_user_id')->constrained('pro_users')->cascadeOnDelete();
            $table->string('kind', 10)->index();                 // offer | need
            $table->string('nature', 30)->nullable();             // product, lot, equipment, stock, rental, service, other
            $table->string('title', 160)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->text('description')->nullable();

            $table->decimal('quantity', 14, 2)->nullable();
            $table->string('unit', 40)->nullable();
            $table->boolean('quantity_to_define')->default(false); // besoin : quantité à définir avec le professionnel
            $table->decimal('min_order_quantity', 14, 2)->nullable();
            $table->string('min_order_unit', 40)->nullable();

            $table->decimal('price', 14, 2)->nullable();          // offre : prix indicatif
            $table->string('currency', 3)->default('XOF');
            $table->boolean('price_negotiable')->default(false);
            $table->boolean('price_on_request')->default(false);
            $table->string('availability', 20)->nullable();       // offre : immediate | few_days | on_order

            $table->string('country', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('location_detail', 180)->nullable();   // offre : lieu d'expédition ; besoin : commune / quartier

            $table->unsignedSmallInteger('validity_days')->nullable(); // offre
            $table->date('expires_on')->nullable();
            $table->string('deadline', 20)->nullable();           // besoin : asap | 7 | 15 | 30 | date
            $table->date('needed_by')->nullable();

            $table->text('specifications')->nullable();           // besoin
            $table->string('brand', 120)->nullable();
            $table->string('reference', 120)->nullable();
            $table->boolean('alternatives_accepted')->nullable();
            $table->string('budget_mode', 20)->nullable();        // proposals | range
            $table->decimal('budget_min', 14, 2)->nullable();
            $table->decimal('budget_max', 14, 2)->nullable();

            $table->string('status', 20)->default('draft')->index(); // draft | published | withdrawn | expired
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pro_publication_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_publication_id')->constrained('pro_publications')->cascadeOnDelete();
            $table->string('type', 10);                           // photo (public) | document (privé)
            $table->string('disk', 20);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('is_main')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_publication_files');
        Schema::dropIfExists('pro_publications');
    }
};
