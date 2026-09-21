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
       Schema::create('disputes', function (Blueprint $table) {
    $table->id();
    $table->string('order_reference'); // ex: CMD-015
    $table->string('client_name');
    $table->text('reason'); // motif du litige
    $table->text('response')->nullable(); // réponse vendeur
    $table->boolean('escalated')->default(false); // escaladé ou non
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
