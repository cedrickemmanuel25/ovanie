<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deletion_in_progress')) {
                $table->boolean('deletion_in_progress')->default(false)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deletion_in_progress')) {
                $table->dropIndex(['deletion_in_progress']);
                $table->dropColumn('deletion_in_progress');
            }
        });
    }
};
