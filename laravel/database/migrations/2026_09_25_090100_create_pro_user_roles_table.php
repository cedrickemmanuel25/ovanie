<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pro_user_roles')) {
            return;
        }

        Schema::create('pro_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_user_id')->constrained('pro_users')->cascadeOnDelete();
            $table->string('role', 50)->index();
            $table->timestamps();

            $table->unique(['pro_user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_user_roles');
    }
};
