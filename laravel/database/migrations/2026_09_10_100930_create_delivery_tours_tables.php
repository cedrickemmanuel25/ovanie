<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_tours', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('driver_id')->nullable()->constrained('delivery_drivers')->nullOnDelete();
            $table->string('vehicle_code')->nullable();
            $table->string('vehicle_label')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('zone_label')->nullable();
            $table->string('status')->default('planned'); // planned, in_progress, late, done, cancelled
            $table->date('tour_date')->nullable();
            $table->time('departure_time')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('optimization_enabled')->default(true);
            $table->unsignedInteger('distance_km')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_tour_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_tour_id')->constrained('delivery_tours')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('commune')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, done
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_tour_stops');
        Schema::dropIfExists('delivery_tours');
    }
};
