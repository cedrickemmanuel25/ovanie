<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pro_users')) {
            return;
        }

        Schema::create('pro_users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable()->unique();
            $table->string('password');
            $table->enum('account_type', ['simple', 'pro'])->default('simple')->index();
            $table->enum('status', ['active', 'suspended'])->default('active')->index();
            $table->enum('professional_status', [
                'not_started',
                'draft',
                'pending',
                'verified',
                'refused',
                'suspended',
            ])->default('not_started')->index();
            $table->string('country', 120)->nullable();
            $table->string('locale', 10)->default('fr');
            $table->string('currency', 10)->default('XOF');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_users');
    }
};
