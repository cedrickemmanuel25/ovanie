<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'commercial_notes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('commercial_notes')->nullable()->after('account_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'commercial_notes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('commercial_notes');
            });
        }
    }
};
