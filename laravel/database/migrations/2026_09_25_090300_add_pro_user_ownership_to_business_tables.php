<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['appel_offres', 'devis', 'business_requests', 'payments', 'business_contact_accesses'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'pro_user_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('pro_user_id')
                    ->nullable()
                    ->constrained('pro_users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['business_contact_accesses', 'payments', 'business_requests', 'devis', 'appel_offres'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'pro_user_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('pro_user_id');
            });
        }
    }
};
