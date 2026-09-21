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

        $missingOriginalPath = ! Schema::hasColumn('product_images', 'original_path');
        $missingCardPath = ! Schema::hasColumn('product_images', 'card_path');
        $missingThumbPath = ! Schema::hasColumn('product_images', 'thumb_path');
        $missingOriginalWidth = ! Schema::hasColumn('product_images', 'original_width');
        $missingOriginalHeight = ! Schema::hasColumn('product_images', 'original_height');
        $missingNormalizedAt = ! Schema::hasColumn('product_images', 'normalized_at');

        Schema::table('product_images', function (Blueprint $table) use (
            $missingOriginalPath,
            $missingCardPath,
            $missingThumbPath,
            $missingOriginalWidth,
            $missingOriginalHeight,
            $missingNormalizedAt
        ) {
            if ($missingOriginalPath) {
                $table->string('original_path')->nullable()->after('path');
            }

            if ($missingCardPath) {
                $table->string('card_path')->nullable()->after('original_path');
            }

            if ($missingThumbPath) {
                $table->string('thumb_path')->nullable()->after('card_path');
            }

            if ($missingOriginalWidth) {
                $table->unsignedInteger('original_width')->nullable()->after('thumb_path');
            }

            if ($missingOriginalHeight) {
                $table->unsignedInteger('original_height')->nullable()->after('original_width');
            }

            if ($missingNormalizedAt) {
                $table->timestamp('normalized_at')->nullable()->after('original_height');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_images')) {
            return;
        }

        $columns = collect([
            'original_path',
            'original_width',
            'original_height',
            'normalized_at',
        ])->filter(
            fn (string $column): bool => Schema::hasColumn('product_images', $column)
        )->all();

        if ($columns !== []) {
            Schema::table('product_images', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
