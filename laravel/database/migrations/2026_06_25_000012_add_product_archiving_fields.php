<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'archived_at')) {
                $column = $table->timestamp('archived_at')->nullable();

                if (Schema::hasColumn('products', 'is_active')) {
                    $column->after('is_active');
                }
            }

            if (! Schema::hasColumn('products', 'archived_by')) {
                $column = $table->foreignId('archived_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                if (Schema::hasColumn('products', 'archived_at')) {
                    $column->after('archived_at');
                }
            }

            if (! Schema::hasColumn('products', 'archive_reason')) {
                $column = $table->text('archive_reason')->nullable();

                if (Schema::hasColumn('products', 'archived_by')) {
                    $column->after('archived_by');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'archived_by')) {
                $table->dropConstrainedForeignId('archived_by');
            }

            if (Schema::hasColumn('products', 'archive_reason')) {
                $table->dropColumn('archive_reason');
            }

            if (Schema::hasColumn('products', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
