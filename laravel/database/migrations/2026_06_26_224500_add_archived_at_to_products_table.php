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

        if (! Schema::hasColumn('products', 'archived_at')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'status')) {
                    $table->timestamp('archived_at')->nullable()->after('status');
                } elseif (Schema::hasColumn('products', 'deleted_at')) {
                    $table->timestamp('archived_at')->nullable()->after('deleted_at');
                } else {
                    $table->timestamp('archived_at')->nullable();
                }
            });
        }

        if (! Schema::hasColumn('products', 'archived_by')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasTable('users')) {
                    $table->foreignId('archived_by')
                        ->nullable()
                        ->after('archived_at')
                        ->constrained('users')
                        ->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('archived_by')->nullable()->after('archived_at');
                }
            });
        }

        if (! Schema::hasColumn('products', 'archive_reason')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('archive_reason', 255)->nullable()->after('archived_by');
            });
        }

        // Index court compatible MySQL/cPanel.
        Schema::table('products', function (Blueprint $table) {
            try {
                $table->index(['shop_id', 'archived_at'], 'products_shop_archived_idx');
            } catch (Throwable $e) {
                // L'index existe peut-être déjà ou shop_id peut être absent selon une ancienne base.
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropIndex('products_shop_archived_idx');
            } catch (Throwable $e) {
                // Ignorer si l'index n'existe pas.
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'archive_reason')) {
                $table->dropColumn('archive_reason');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'archived_by')) {
                try {
                    $table->dropConstrainedForeignId('archived_by');
                } catch (Throwable $e) {
                    $table->dropColumn('archived_by');
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
