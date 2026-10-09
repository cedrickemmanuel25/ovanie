<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pro_users') || Schema::hasColumn('pro_users', 'professional_profile')) {
            return;
        }

        Schema::table('pro_users', function (Blueprint $table) {
            $table->string('professional_profile', 80)
                ->nullable()
                ->after('account_type')
                ->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pro_users') || ! Schema::hasColumn('pro_users', 'professional_profile')) {
            return;
        }

        Schema::table('pro_users', function (Blueprint $table) {
            $table->dropIndex(['professional_profile']);
            $table->dropColumn('professional_profile');
        });
    }
};
