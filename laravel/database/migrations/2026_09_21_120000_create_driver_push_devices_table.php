<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_push_devices')) {
            return;
        }

        Schema::create('driver_push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('delivery_drivers')->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 30)->default('android');
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('locale', 20)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_code', 120)->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_push_devices');
    }
};
