<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_images')) {
            return;
        }

        if (! Schema::hasColumn('product_images', 'original_path')) {
            Schema::table('product_images', function (Blueprint $table): void {
                $table->string('original_path')->nullable()->after('path');
            });
        }

        if (! Schema::hasColumn('product_images', 'card_path')) {
            Schema::table('product_images', function (Blueprint $table): void {
                $table->string('card_path')->nullable()->after('path');
            });
        }

        if (! Schema::hasColumn('product_images', 'thumb_path')) {
            Schema::table('product_images', function (Blueprint $table): void {
                $table->string('thumb_path')->nullable()->after('card_path');
            });
        }

        if (! Schema::hasColumn('product_images', 'original_width')) {
            Schema::table('product_images', function (Blueprint $table): void {
                $table->unsignedInteger('original_width')->nullable()->after('thumb_path');
            });
        }

        if (! Schema::hasColumn('product_images', 'original_height')) {
            Schema::table('product_images', function (Blueprint $table): void {
                $table->unsignedInteger('original_height')->nullable()->after('original_width');
            });
        }

        if (! Schema::hasColumn('product_images', 'normalized_at')) {
            Schema::table('product_images', function (Blueprint $table): void {
                $table->timestamp('normalized_at')->nullable()->after('original_height');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_images')) {
            return;
        }

        // Ne supprime que les colonnes propres à la V2. card_path, thumb_path et
        // normalized_at peuvent déjà appartenir à une migration antérieure.
        foreach (['original_height', 'original_width', 'original_path'] as $column) {
            if (Schema::hasColumn('product_images', $column)) {
                Schema::table('product_images', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
