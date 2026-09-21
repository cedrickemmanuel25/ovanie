<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('status')->default('active');
            $table->decimal('rating', 3, 1)->default(4.5);
            $table->unsignedSmallInteger('average_delay_hours')->default(48);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['status', 'is_active']);
        });

        Schema::create('carrier_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('plate_number')->nullable();
            $table->decimal('capacity_ton', 8, 2)->default(0);
            $table->decimal('volume_m3', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['carrier_id', 'is_active']);
        });

        Schema::create('carrier_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();
            $table->string('zone')->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->decimal('price_per_km', 12, 2)->default(0);
            $table->decimal('price_per_ton', 12, 2)->default(0);
            $table->decimal('zone_surcharge', 12, 2)->default(0);
            $table->decimal('urgency_surcharge', 12, 2)->default(0);
            $table->decimal('fragile_surcharge', 12, 2)->default(0);
            $table->decimal('unloading_surcharge', 12, 2)->default(0);
            $table->unsignedSmallInteger('estimated_delay_hours')->default(48);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['zone', 'is_active']);
        });

        Schema::create('delivery_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin_city')->nullable();
            $table->string('destination_city')->nullable();
            $table->string('zone')->nullable();
            $table->decimal('distance_km', 10, 2)->default(0);
            $table->decimal('weight_ton', 10, 2)->default(0);
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_fragile')->default(false);
            $table->boolean('requires_unloading')->default(false);
            $table->unsignedSmallInteger('estimated_delay_hours')->nullable();
            $table->decimal('final_price', 12, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['carrier_id', 'order_id']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('carrier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracking_number')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('final_price', 12, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['carrier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('delivery_quotes');
        Schema::dropIfExists('carrier_rate_cards');
        Schema::dropIfExists('carrier_vehicles');
        Schema::dropIfExists('carriers');
    }
};
