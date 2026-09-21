<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shops', 'display_name')) {
            Schema::table('shops', function (Blueprint $table): void {
                $table->string('display_name', 160)->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('shops', 'main_subcategory')) {
            Schema::table('shops', function (Blueprint $table): void {
                $table->string('main_subcategory', 160)->nullable()->after('main_category');
            });
        }

        if (! Schema::hasColumn('shops', 'commercial_notes')) {
            Schema::table('shops', function (Blueprint $table): void {
                $table->text('commercial_notes')->nullable()->after('main_subcategory');
            });
        }
    }

    public function down(): void
    {
        foreach (['commercial_notes', 'main_subcategory', 'display_name'] as $column) {
            if (Schema::hasColumn('shops', $column)) {
                Schema::table('shops', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
