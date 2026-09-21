<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        /*
         * MySQL/MariaDB support ALTER TABLE ... MODIFY ... ENUM.
         * SQLite, used by Laravel tests with :memory:, does not support MODIFY
         * or ENUM. In SQLite we keep the existing role column as-is so the
         * test database can migrate successfully.
         */
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('client','vendor','admin','logistique') NOT NULL DEFAULT 'client'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("UPDATE users SET role = 'client' WHERE role = 'logistique'");
        DB::statement("ALTER TABLE users MODIFY role ENUM('client','vendor','admin') NOT NULL DEFAULT 'client'");
    }
};
