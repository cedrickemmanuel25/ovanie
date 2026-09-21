<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_reception_forms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();

            $table->string('client_code')->nullable();
            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_cni')->nullable();

            $table->string('delivery_place')->nullable();
            $table->string('reception_place')->nullable();
            $table->string('courier_name')->nullable();

            $table->string('status')->default('draft'); // draft | validated
            $table->timestamp('validated_at')->nullable();

            $table->string('validated_city')->nullable();
            $table->date('validated_date')->nullable();

            $table->timestamps();

            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reception_forms');
    }
};