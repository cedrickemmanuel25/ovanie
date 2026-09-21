<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Statut vendeur sur les lignes de commande
        |--------------------------------------------------------------------------
        | Le tableau de bord vendeur filtre déjà les lignes de commande avec :
        | order_items.vendor_status
        |
        | Sur la production, cette colonne n'existe pas encore, ce qui provoque :
        | Unknown column 'vendor_status' in 'WHERE'
        |
        | Cette migration ajoute la colonne de façon sécurisée.
        */

        if (! Schema::hasColumn('order_items', 'vendor_status')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (Schema::hasColumn('order_items', 'status')) {
                    $table->string('vendor_status', 50)
                        ->default('pending')
                        ->after('status');
                } elseif (Schema::hasColumn('order_items', 'delivery_status')) {
                    $table->string('vendor_status', 50)
                        ->default('pending')
                        ->after('delivery_status');
                } else {
                    $table->string('vendor_status', 50)
                        ->default('pending');
                }

                $table->index('vendor_status', 'order_items_vendor_status_idx');
            });
        }

        if (! Schema::hasColumn('order_items', 'vendor_validated_at')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->timestamp('vendor_validated_at')
                    ->nullable()
                    ->after('vendor_status');
            });
        }

        if (! Schema::hasColumn('order_items', 'vendor_cancelled_at')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->timestamp('vendor_cancelled_at')
                    ->nullable()
                    ->after('vendor_validated_at');
            });
        }

        if (! Schema::hasColumn('order_items', 'vendor_note')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->text('vendor_note')
                    ->nullable()
                    ->after('vendor_cancelled_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'vendor_status')) {
                $table->dropIndex('order_items_vendor_status_idx');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $columns = [
                'vendor_note',
                'vendor_cancelled_at',
                'vendor_validated_at',
                'vendor_status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
