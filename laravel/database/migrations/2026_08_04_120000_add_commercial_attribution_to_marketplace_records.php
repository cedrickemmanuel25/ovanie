<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addNullableId('users',    'created_by_commercial_id', 'id');
        $this->addNullableId('shops',    'created_by_commercial_id', 'user_id');
        $this->addNullableId('shops',    'managed_by_commercial_id', 'created_by_commercial_id');
        // 'shop_id' peut ne pas exister dans products selon la version du schéma
        // → on cherche dynamiquement la première colonne disponible comme ancre
        $productsAnchor = collect(['shop_id', 'user_id', 'id'])
            ->first(fn ($col) => Schema::hasColumn('products', $col)) ?? null;
        $this->addNullableId('products', 'created_by_commercial_id', $productsAnchor);

        // Les noms sont explicites car certaines versions MariaDB/cPanel
        // génèrent sinon une contrainte appelée "1", unique dans tout le schéma.
        $this->ensureForeign('users',    'created_by_commercial_id', 'users_created_by_commercial_fk');
        $this->ensureForeign('shops',    'created_by_commercial_id', 'shops_created_by_commercial_fk');
        $this->ensureForeign('shops',    'managed_by_commercial_id', 'shops_managed_by_commercial_fk');
        $this->ensureForeign('products', 'created_by_commercial_id', 'products_created_by_commercial_fk');
    }

    public function down(): void
    {
        $this->dropColumnWithForeign('products', 'created_by_commercial_id');
        $this->dropColumnWithForeign('shops', 'managed_by_commercial_id');
        $this->dropColumnWithForeign('shops', 'created_by_commercial_id');
        $this->dropColumnWithForeign('users', 'created_by_commercial_id');
    }

    private function addNullableId(string $tableName, string $column, ?string $after): void
    {
        if (Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $after) {
            $col = $table->unsignedBigInteger($column)->nullable();
            // N'utiliser after() que si la colonne d'ancrage existe réellement
            if ($after !== null && Schema::hasColumn($table->getTable(), $after)) {
                $col->after($after);
            }
        });
    }

    private function ensureForeign(string $tableName, string $column, string $expectedName): void
    {
        $existingName = $this->foreignName($tableName, $column);

        if ($existingName === $expectedName) {
            return;
        }

        if ($existingName !== null) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign($existingName));
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $expectedName) {
            $table->foreign($column, $expectedName)
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    private function dropColumnWithForeign(string $tableName, string $column): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            return;
        }

        $foreignName = $this->foreignName($tableName, $column);
        if ($foreignName !== null) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign($foreignName));
        }

        Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
    }

    private function foreignName(string $tableName, string $column): ?string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite does not support information_schema; we cannot reliably
            // retrieve a named foreign key constraint. Return null so the caller
            // will simply create the constraint without trying to drop an old one.
            return null;
        }

        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS constraint_name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$tableName, $column],
        );

        return $row?->constraint_name ?: null;
    }
};
