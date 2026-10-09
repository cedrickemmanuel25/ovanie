<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('type', 80)->default('Couleur');
                $table->string('value', 120);
                $table->string('color_hex', 16)->nullable();
                $table->integer('stock')->default(0);
                $table->decimal('price_override', 12, 2)->nullable();
                $table->string('image_path')->nullable();
                $table->string('card_path')->nullable();
                $table->string('thumb_path')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['product_id', 'type', 'value'], 'product_variant_unique');
            });
        }

        if (Schema::hasTable('cart_items') && ! Schema::hasColumn('cart_items', 'product_variant_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->foreignId('product_variant_id')->nullable()->after('product_id')
                    ->constrained('product_variants')->nullOnDelete();
                $table->string('variant_label_snapshot')->nullable()->after('product_variant_id');
                $table->string('variant_image_snapshot')->nullable()->after('variant_label_snapshot');
                $table->index(['cart_id', 'product_id', 'product_variant_id'], 'cart_variant_lookup');
            });
        }

        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'product_variant_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreignId('product_variant_id')->nullable()->after('product_id')
                    ->constrained('product_variants')->nullOnDelete();
                $table->string('variant_label_snapshot')->nullable()->after('product_variant_id');
                $table->string('variant_image_snapshot')->nullable()->after('variant_label_snapshot');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'product_variant_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_variant_id');
                $table->dropColumn(['variant_label_snapshot', 'variant_image_snapshot']);
            });
        }
        if (Schema::hasTable('cart_items') && Schema::hasColumn('cart_items', 'product_variant_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropIndex('cart_variant_lookup');
                $table->dropConstrainedForeignId('product_variant_id');
                $table->dropColumn(['variant_label_snapshot', 'variant_image_snapshot']);
            });
        }
        Schema::dropIfExists('product_variants');
    }
};
