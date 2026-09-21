<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_reception_form_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_reception_form_id')
                ->constrained('order_reception_forms')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('order_item_id')->nullable();

            $table->string('product_name');
            $table->string('shop_name')->nullable();

            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);

            $table->boolean('received')->default(false);
            $table->date('received_date')->nullable();
            $table->time('received_time')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reception_form_items');
    }
};