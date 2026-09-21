<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'transport')) {
                $table->decimal('transport', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'availability_status')) {
                $table->string('availability_status', 30)->default('in_stock');
            }
            if (! Schema::hasColumn('products', 'content_per_unit')) {
                $table->decimal('content_per_unit', 12, 3)->nullable();
            }
            if (! Schema::hasColumn('products', 'content_unit')) {
                $table->string('content_unit', 50)->nullable();
            }
            if (! Schema::hasColumn('products', 'units_per_package')) {
                $table->unsignedInteger('units_per_package')->nullable();
            }
            if (! Schema::hasColumn('products', 'coverage_per_unit_m2')) {
                $table->decimal('coverage_per_unit_m2', 12, 4)->nullable();
            }
            if (! Schema::hasColumn('products', 'seller_delivery_delay')) {
                $table->string('seller_delivery_delay', 100)->nullable();
            }
            if (! Schema::hasColumn('products', 'pickup_city')) {
                $table->string('pickup_city', 150)->nullable();
            }
            if (! Schema::hasColumn('products', 'pickup_commune')) {
                $table->string('pickup_commune', 150)->nullable();
            }
            if (! Schema::hasColumn('products', 'pickup_address')) {
                $table->string('pickup_address')->nullable();
            }
            if (! Schema::hasColumn('products', 'product_attributes')) {
                $table->json('product_attributes')->nullable();
            }
            if (! Schema::hasColumn('products', 'handling_options')) {
                $table->json('handling_options')->nullable();
            }
            if (! Schema::hasColumn('products', 'product_video_path')) {
                $table->string('product_video_path')->nullable();
            }
            if (! Schema::hasColumn('products', 'product_video_url')) {
                $table->string('product_video_url', 500)->nullable();
            }
            if (! Schema::hasColumn('products', 'boost_price')) {
                $table->decimal('boost_price', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'boost_payment_reference')) {
                $table->string('boost_payment_reference')->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'boost_paid_at')) {
                $table->timestamp('boost_paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $columns = [
            'transport',
            'availability_status',
            'content_per_unit',
            'content_unit',
            'units_per_package',
            'coverage_per_unit_m2',
            'seller_delivery_delay',
            'pickup_city',
            'pickup_commune',
            'pickup_address',
            'product_attributes',
            'handling_options',
            'product_video_path',
            'product_video_url',
            'boost_price',
            'boost_payment_reference',
            'boost_paid_at',
        ];

        Schema::table('products', function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
