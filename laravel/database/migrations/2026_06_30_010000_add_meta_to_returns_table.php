<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('returns') || Schema::hasColumn('returns', 'meta')) {
            return;
        }

        Schema::table('returns', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('returns') || ! Schema::hasColumn('returns', 'meta')) {
            return;
        }

        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
