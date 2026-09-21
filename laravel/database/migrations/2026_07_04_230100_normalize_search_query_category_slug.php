<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('search_queries') || ! Schema::hasColumn('search_queries', 'category_slug')) {
            return;
        }

        DB::table('search_queries')
            ->whereNull('category_slug')
            ->update(['category_slug' => '']);
    }

    public function down(): void
    {
        // Normalisation non destructive : aucun rollback nécessaire.
    }
};
