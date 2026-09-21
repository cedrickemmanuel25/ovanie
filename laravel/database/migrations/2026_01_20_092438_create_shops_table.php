<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShopsTable extends Migration
{
    public function up()
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->engine = 'InnoDB';  // Ajout du moteur InnoDB
            $table->id();

            // Utilisateur propriétaire de la boutique
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();

            // Type de vendeur : particulier ou entreprise
            $table->enum('seller_type', ['particulier', 'entreprise']);

            $table->string('city');
            $table->string('address')->nullable();

            $table->string('identity_type');
            $table->string('identity_number');
            $table->string('identity_file')->nullable();

            // Mobile Money info
            $table->string('mm_operator');
            $table->string('mm_number');
            $table->string('mm_holder');

            $table->boolean('direct_payment')->default(false);

            // Statut de validation
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->boolean('is_active')->default(false);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('shops');
    }
}
