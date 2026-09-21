<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->engine = 'InnoDB';  // Ajout du moteur InnoDB
            $table->id();

            // Identité
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();

            // Contact
            $table->string('email')->unique();
            $table->string('phone')->unique()->nullable();

            // Auth
            $table->string('password');
            $table->enum('role', ['client', 'vendor', 'admin', 'logistique'])->default('client');
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            // Admin custom verification (si utilisé)
            $table->string('email_verification_token')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
