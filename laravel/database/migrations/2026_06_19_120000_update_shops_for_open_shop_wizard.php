<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE shops MODIFY seller_type ENUM('particulier','entreprise','artisan','grossiste') NOT NULL");
            DB::statement('ALTER TABLE shops MODIFY mm_operator VARCHAR(255) NULL');
            DB::statement('ALTER TABLE shops MODIFY mm_number VARCHAR(255) NULL');
            DB::statement('ALTER TABLE shops MODIFY mm_holder VARCHAR(255) NULL');

            return;
        }

        Schema::table('shops', function (Blueprint $table) {
            $table->string('seller_type')->change();
            $table->string('mm_operator')->nullable()->change();
            $table->string('mm_number')->nullable()->change();
            $table->string('mm_holder')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE shops SET seller_type = 'particulier' WHERE seller_type IN ('artisan','grossiste')");
            DB::statement("UPDATE shops SET mm_operator = '' WHERE mm_operator IS NULL");
            DB::statement("UPDATE shops SET mm_number = '' WHERE mm_number IS NULL");
            DB::statement("UPDATE shops SET mm_holder = '' WHERE mm_holder IS NULL");
            DB::statement("ALTER TABLE shops MODIFY seller_type ENUM('particulier','entreprise') NOT NULL");
            DB::statement('ALTER TABLE shops MODIFY mm_operator VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE shops MODIFY mm_number VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE shops MODIFY mm_holder VARCHAR(255) NOT NULL');
        }
    }
};
