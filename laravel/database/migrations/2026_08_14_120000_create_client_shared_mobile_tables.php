<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recently_viewed_products')) {
            Schema::create('recently_viewed_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('views_count')->default(1);
                $table->timestamp('last_viewed_at')->useCurrent()->index();
                $table->timestamps();

                $table->unique(['user_id', 'product_id']);
                $table->index(['user_id', 'last_viewed_at']);
            });
        }

        if (! Schema::hasTable('order_experience_reviews')) {
            Schema::create('order_experience_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('delivery_rating');
                $table->text('delivery_comment')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_experience_reviews');
        Schema::dropIfExists('recently_viewed_products');
    }
};
