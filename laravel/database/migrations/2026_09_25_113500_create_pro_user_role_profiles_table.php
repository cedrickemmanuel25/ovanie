<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pro_user_role_profiles')) {
            return;
        }

        Schema::create('pro_user_role_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_user_role_id')->unique()->constrained('pro_user_roles')->cascadeOnDelete();
            $table->string('business_name', 180)->nullable();
            $table->string('activity', 180)->nullable();
            $table->string('country', 100)->nullable()->index();
            $table->string('city', 120)->nullable();
            $table->string('professional_email')->nullable();
            $table->string('professional_phone', 40)->nullable();
            $table->json('profile_data')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_user_role_profiles');
    }
};
