<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('master_products')) {
            return;
        }

        Schema::table('master_products', function (Blueprint $table) {
            if (! Schema::hasColumn('master_products', 'short_description')) {
                $table->text('short_description')->nullable()->after('description');
            }
            if (! Schema::hasColumn('master_products', 'technical_details')) {
                $table->longText('technical_details')->nullable()->after('short_description');
            }
            if (! Schema::hasColumn('master_products', 'product_attributes')) {
                $table->json('product_attributes')->nullable()->after('technical_details');
            }
            if (! Schema::hasColumn('master_products', 'fragile')) {
                $table->boolean('fragile')->nullable()->after('height_cm');
            }
            if (! Schema::hasColumn('master_products', 'requires_unloading')) {
                $table->boolean('requires_unloading')->nullable()->after('fragile');
            }
            if (! Schema::hasColumn('master_products', 'unloading_instructions')) {
                $table->text('unloading_instructions')->nullable()->after('requires_unloading');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('master_products')) {
            return;
        }

        $columns = [
            'short_description',
            'technical_details',
            'product_attributes',
            'fragile',
            'requires_unloading',
            'unloading_instructions',
        ];

        $existing = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn('master_products', $column)
        ));

        if ($existing !== []) {
            Schema::table('master_products', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
