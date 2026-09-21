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

        if (! Schema::hasColumn('product_images', 'card_path')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->string('card_path')
                    ->nullable()
                    ->after('path');
            });
        }

        if (! Schema::hasColumn('product_images', 'thumb_path')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->string('thumb_path')
                    ->nullable()
                    ->after('card_path');
            });
        }

        if (! Schema::hasColumn('product_images', 'normalized_at')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->timestamp('normalized_at')
                    ->nullable()
                    ->after('thumb_path');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_images')) {
            return;
        }

        $columns = collect([
            'card_path',
            'thumb_path',
            'normalized_at',
        ])->filter(
            fn (string $column): bool => Schema::hasColumn(
                'product_images',
                $column
            )
        )->values()->all();

        if ($columns !== []) {
            Schema::table('product_images', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
