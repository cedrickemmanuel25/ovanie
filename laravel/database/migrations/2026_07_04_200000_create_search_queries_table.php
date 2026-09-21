<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_queries')) {
            return;
        }

        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('term', 120);
            $table->string('normalized_term', 120);
            $table->string('category_slug', 160)->default('');
            $table->unsignedBigInteger('searches_count')->default(1);
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();

            $table->unique(['normalized_term', 'category_slug'], 'search_queries_term_category_unique');
            $table->index(['searches_count', 'last_searched_at'], 'search_queries_popular_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
