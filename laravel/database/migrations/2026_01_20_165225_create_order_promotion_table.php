<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderPromotionTable extends Migration
{
    public function up()
    {
        Schema::create('order_promotion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'promotion_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_promotion');
    }
}
