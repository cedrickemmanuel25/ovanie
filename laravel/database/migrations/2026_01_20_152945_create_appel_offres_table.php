<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppelOffresTable extends Migration
{
    public function up()
    {
        Schema::create('appel_offres', function (Blueprint $table) {
            $table->id();
            $table->string('secteur');
            $table->json('services'); // tableau JSON des services
            $table->string('prenom');
            $table->string('nom');
            $table->string('email');
            $table->string('telephone')->nullable();
            $table->string('pays')->nullable();
            $table->string('ville')->nullable();
            $table->string('image')->nullable(); // chemin image
            $table->decimal('budget', 10, 2);
            $table->integer('delai')->nullable();
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('appel_offres');
    }
}
