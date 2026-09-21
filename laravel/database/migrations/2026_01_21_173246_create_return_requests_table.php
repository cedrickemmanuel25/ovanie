<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('order_reference');
            $table->string('product_name');
            $table->text('reason');
            $table->date('request_date');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'closed', 'refunded', 'resolved', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
