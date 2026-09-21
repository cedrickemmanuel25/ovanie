<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            $this->addIndexIfPossible(
                'products',
                ['status', 'is_active', 'sale_type', 'created_at'],
                'products_home_sale_idx'
            );

            $this->addIndexIfPossible(
                'products',
                ['status', 'is_active', 'sales'],
                'products_home_sales_idx'
            );

            $this->addIndexIfPossible(
                'products',
                ['free_boosted_by_ovanie', 'free_boost_end_at'],
                'products_free_boost_idx'
            );

            $this->addIndexIfPossible(
                'products',
                ['is_boosted', 'boost_end_at'],
                'products_paid_boost_idx'
            );
        }

        if (Schema::hasTable('banners')) {
            $this->addIndexIfPossible(
                'banners',
                ['is_active', 'zone', 'position'],
                'banners_home_zone_idx'
            );
        }

        if (Schema::hasTable('home_ads')) {
            $this->addIndexIfPossible(
                'home_ads',
                ['is_active', 'placement', 'sort_order'],
                'home_ads_active_placement_idx'
            );
        }

        if (Schema::hasTable('categories')) {
            $this->addIndexIfPossible(
                'categories',
                ['status', 'is_active', 'parent_id', 'sort_order'],
                'categories_home_active_idx'
            );
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('products', 'products_home_sale_idx');
        $this->dropIndexIfExists('products', 'products_home_sales_idx');
        $this->dropIndexIfExists('products', 'products_free_boost_idx');
        $this->dropIndexIfExists('products', 'products_paid_boost_idx');
        $this->dropIndexIfExists('banners', 'banners_home_zone_idx');
        $this->dropIndexIfExists('home_ads', 'home_ads_active_placement_idx');
        $this->dropIndexIfExists('categories', 'categories_home_active_idx');
    }

    private function addIndexIfPossible(string $table, array $columns, string $name): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if (Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }
};
