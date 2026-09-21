<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'products' => [
            ['columns' => ['shop_id'], 'name' => 'products_shop_id_idx'],
            ['columns' => ['category_id'], 'name' => 'products_category_id_idx'],
            ['columns' => ['status'], 'name' => 'products_status_idx'],
            ['columns' => ['city'], 'name' => 'products_city_idx'],
            ['columns' => ['status', 'category_id'], 'name' => 'products_status_category_idx'],
            ['columns' => ['status', 'shop_id'], 'name' => 'products_status_shop_idx'],
        ],
        'orders' => [
            ['columns' => ['user_id'], 'name' => 'orders_user_id_idx'],
            ['columns' => ['client_id'], 'name' => 'orders_client_id_idx'],
            ['columns' => ['status'], 'name' => 'orders_status_idx'],
            ['columns' => ['created_at'], 'name' => 'orders_created_at_idx'],
        ],
        'order_items' => [
            ['columns' => ['shop_id'], 'name' => 'order_items_shop_id_idx'],
            ['columns' => ['order_id'], 'name' => 'order_items_order_id_idx'],
            ['columns' => ['product_id'], 'name' => 'order_items_product_id_idx'],
        ],
        'payments' => [
            ['columns' => ['order_id'], 'name' => 'payments_order_id_idx'],
            ['columns' => ['status'], 'name' => 'payments_status_idx'],
        ],
        'shipments' => [
            ['columns' => ['order_id'], 'name' => 'shipments_order_id_idx'],
            ['columns' => ['status'], 'name' => 'shipments_status_idx'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $definitions) {
                foreach ($definitions as $definition) {
                    if ($this->columnsExist($table, $definition['columns']) && ! $this->indexExists($table, $definition['name'])) {
                        $blueprint->index($definition['columns'], $definition['name']);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $definitions) {
                foreach ($definitions as $definition) {
                    if ($this->indexExists($table, $definition['name'])) {
                        $blueprint->dropIndex($definition['name']);
                    }
                }
            });
        }
    }

    private function columnsExist(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select('PRAGMA index_list(' . $this->quoteSqliteIdentifier($table) . ')');

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $database = DB::getDatabaseName();

            $result = DB::selectOne(
                'SELECT COUNT(1) as total FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                [$database, $table, $indexName]
            );

            return (int) ($result->total ?? 0) > 0;
        }

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                'SELECT COUNT(1) as total FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
                [$table, $indexName]
            );

            return (int) ($result->total ?? 0) > 0;
        }

        return false;
    }

    private function quoteSqliteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
};
