<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('home_ads')) {
            Schema::table('home_ads', function (Blueprint $table) {
                if (!Schema::hasColumn('home_ads', 'placement')) {
                    $table->string('placement')->nullable()->after('id');
                }

                if (!Schema::hasColumn('home_ads', 'subtitle')) {
                    $table->string('subtitle')->nullable()->after('title');
                }

                if (!Schema::hasColumn('home_ads', 'button_text')) {
                    $table->string('button_text')->nullable()->after('subtitle');
                }

                if (!Schema::hasColumn('home_ads', 'button_url')) {
                    $table->string('button_url')->nullable()->after('button_text');
                }

                if (!Schema::hasColumn('home_ads', 'starts_at')) {
                    $table->timestamp('starts_at')->nullable();
                }

                if (!Schema::hasColumn('home_ads', 'ends_at')) {
                    $table->timestamp('ends_at')->nullable();
                }

                if (!Schema::hasColumn('home_ads', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'parent_id')) {
                    $table->unsignedBigInteger('parent_id')->nullable()->after('id');
                }

                if (!Schema::hasColumn('categories', 'level')) {
                    $table->unsignedTinyInteger('level')->default(1)->after('parent_id');
                }

                if (!Schema::hasColumn('categories', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0);
                }

                if (!Schema::hasColumn('categories', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};
