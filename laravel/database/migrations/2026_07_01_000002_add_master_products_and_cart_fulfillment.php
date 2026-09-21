<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('master_products')) {
            Schema::create('master_products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('brand')->nullable();
                $table->string('reference')->nullable();
                $table->string('sku')->nullable();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->string('unit')->nullable();
                $table->string('packaging')->nullable();
                $table->decimal('weight_kg', 12, 3)->nullable();
                $table->decimal('volume_m3', 12, 4)->nullable();
                $table->decimal('length_cm', 12, 2)->nullable();
                $table->decimal('width_cm', 12, 2)->nullable();
                $table->decimal('height_cm', 12, 2)->nullable();
                $table->string('color')->nullable();
                $table->string('grade')->nullable();
                $table->string('standard')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['brand', 'reference', 'sku'], 'master_products_identity_idx');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $this->unsignedBigIntegerColumn($table, 'master_product_id');
                $this->unsignedIntegerColumn($table, 'fulfillment_priority', 100);
                $this->booleanColumn($table, 'is_fulfillment_enabled', true);
                $this->stringColumn($table, 'sku', 100);
                $this->stringColumn($table, 'brand', 255);
                $this->stringColumn($table, 'unit', 80);
                $this->stringColumn($table, 'packaging', 255);
                $this->stringColumn($table, 'material_grade', 255);
                $this->stringColumn($table, 'color', 120);
                $this->stringColumn($table, 'standard', 120);
            });
        }

        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $this->unsignedBigIntegerColumn($table, 'original_product_id');
                $this->unsignedBigIntegerColumn($table, 'fulfillment_product_id');
                $this->unsignedBigIntegerColumn($table, 'original_shop_id');
                $this->unsignedBigIntegerColumn($table, 'fulfillment_shop_id');
                $this->booleanColumn($table, 'optimization_applied', false);
                $this->decimalColumn($table, 'optimization_savings');
                $this->jsonColumn($table, 'optimization_meta');
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $this->unsignedBigIntegerColumn($table, 'original_product_id');
                $this->unsignedBigIntegerColumn($table, 'fulfilled_product_id');
                $this->unsignedBigIntegerColumn($table, 'original_shop_id');
                $this->unsignedBigIntegerColumn($table, 'fulfilled_shop_id');
                $this->booleanColumn($table, 'optimization_applied', false);
                $this->decimalColumn($table, 'optimization_savings');
                $this->jsonColumn($table, 'optimization_meta');
            });
        }

        if (! Schema::hasTable('cart_fulfillment_optimizations')) {
            Schema::create('cart_fulfillment_optimizations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cart_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('cart_item_id')->nullable()->index();
                $table->unsignedBigInteger('original_product_id')->nullable()->index();
                $table->unsignedBigInteger('fulfillment_product_id')->nullable()->index();
                $table->unsignedBigInteger('original_shop_id')->nullable()->index();
                $table->unsignedBigInteger('fulfillment_shop_id')->nullable()->index();
                $table->decimal('original_delivery_fee', 12, 2)->default(0);
                $table->decimal('optimized_delivery_fee', 12, 2)->default(0);
                $table->decimal('savings', 12, 2)->default(0);
                $table->string('reason')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Non destructive: fulfillment audit data is retained.
    }

    private function unsignedBigIntegerColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->unsignedBigInteger($name)->nullable()->index();
        }
    }

    private function unsignedIntegerColumn(Blueprint $table, string $name, int $default): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->unsignedInteger($name)->default($default);
        }
    }

    private function booleanColumn(Blueprint $table, string $name, bool $default): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->boolean($name)->default($default);
        }
    }

    private function decimalColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->decimal($name, 12, 2)->default(0);
        }
    }

    private function jsonColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->json($name)->nullable();
        }
    }

    private function stringColumn(Blueprint $table, string $name, int $length): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->string($name, $length)->nullable();
        }
    }
};
