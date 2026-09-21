<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_zones')) {
            Schema::create('delivery_zones', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('region')->nullable();
                $table->decimal('price', 12, 2)->default(0);
                $table->unsignedSmallInteger('delivery_days')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['name', 'region']);
                $table->index('is_active');
            });
        }

        if (!Schema::hasTable('delivery_drivers')) {
            Schema::create('delivery_drivers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone');
                $table->string('zone')->nullable();
                $table->string('vehicle')->nullable();
                $table->string('status')->default('Disponible');
                $table->decimal('rating', 3, 1)->default(4.5);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['name', 'phone']);
                $table->index(['zone', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_drivers');
        Schema::dropIfExists('delivery_zones');
    }
};
