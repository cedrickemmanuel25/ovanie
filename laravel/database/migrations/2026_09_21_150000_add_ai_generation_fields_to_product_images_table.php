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

        Schema::table('product_images', function (Blueprint $table) {
            if (! Schema::hasColumn('product_images', 'ai_image_status')) {
                $table->string('ai_image_status')->nullable()->after('normalized_at');
            }

            if (! Schema::hasColumn('product_images', 'ai_image_generated_at')) {
                $table->timestamp('ai_image_generated_at')->nullable()->after('ai_image_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_images')) {
            return;
        }

        Schema::table('product_images', function (Blueprint $table) {
            foreach (['ai_image_status', 'ai_image_generated_at'] as $column) {
                if (Schema::hasColumn('product_images', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
