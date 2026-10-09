<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_user_id')->nullable()->constrained('pro_users')->nullOnDelete();
            $table->char('challenge_hash', 64)->unique();
            $table->string('code_hash');
            $table->string('channel', 12)->default('mail');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_password_reset_codes');
    }
};
