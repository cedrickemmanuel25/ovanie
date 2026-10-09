<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['business_requests', 'business_contact_accesses'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'user_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // On ne remet pas user_id en NOT NULL au rollback : des lignes créées
        // par OVANIE Pro peuvent légitimement avoir user_id = NULL.
    }

};
