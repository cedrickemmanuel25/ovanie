<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('logistics_pricing_simulations')) {
            return;
        }

        Schema::create('logistics_pricing_simulations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('origin_commune_id')->nullable()->index();
            $table->unsignedBigInteger('destination_commune_id')->nullable()->index();
            $table->string('origin_label', 160);
            $table->string('destination_label', 160);
            $table->string('vehicle_code', 40)->index();
            $table->decimal('weight_kg', 12, 3)->default(0);
            $table->decimal('volume_m3', 12, 4)->default(0);
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('price_total', 12, 2)->default(0);
            $table->string('pricing_source', 80)->nullable()->index();
            $table->json('components')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'vehicle_code'], 'lps_created_vehicle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_pricing_simulations');
    }
};
