<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_category')) {
            Schema::create('shop_category', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('category_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['shop_id', 'category_id']);
            });
        }

        // Reprend l'unique catégorie déjà choisie par chaque boutique existante
        // (colonne main_category, un slug) pour peupler la nouvelle relation
        // many-to-many sans perdre de données.
        if (Schema::hasTable('shops') && Schema::hasTable('categories') && Schema::hasColumn('shops', 'main_category')) {
            DB::table('shops')
                ->whereNotNull('main_category')
                ->where('main_category', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($shops) {
                    foreach ($shops as $shop) {
                        $categoryId = DB::table('categories')
                            ->where('slug', $shop->main_category)
                            ->value('id');

                        if (! $categoryId) {
                            continue;
                        }

                        DB::table('shop_category')->insertOrIgnore([
                            'shop_id' => $shop->id,
                            'category_id' => $categoryId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_category');
    }
};
