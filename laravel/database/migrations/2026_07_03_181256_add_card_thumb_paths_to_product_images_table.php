<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les colonnes de variantes d'images normalisées à la table product_images.
     *
     * - card_path  : 800 × 800 px WebP (utilisé dans les cartes catalogue)
     * - thumb_path : 400 × 400 px WebP (utilisé dans les miniatures / listings vendeur)
     * - master_path alias de "path" — path reste le fichier 1200 × 1200 px
     */
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            if (! Schema::hasColumn('product_images', 'card_path')) {
                $table->string('card_path')->nullable()->after('path')
                    ->comment('Variante 800×800 px WebP pour les cartes catalogue');
            }

            if (! Schema::hasColumn('product_images', 'thumb_path')) {
                $table->string('thumb_path')->nullable()->after('card_path')
                    ->comment('Variante 400×400 px WebP pour les miniatures');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumnIfExists('card_path');
            $table->dropColumnIfExists('thumb_path');
        });
    }
};
