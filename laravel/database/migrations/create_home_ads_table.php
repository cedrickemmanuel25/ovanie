<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_ads', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('image'); // image | video
            $table->string('title')->nullable();
            $table->text('text')->nullable();
            $table->string('media');
            $table->string('link')->nullable();
            $table->unsignedInteger('duration')->default(5000); // ms
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_ads');
    }
};