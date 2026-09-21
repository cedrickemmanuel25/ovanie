<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments')
            || ! Schema::hasTable('order_items')
            || ! Schema::hasColumn('shipments', 'order_item_id')) {
            return;
        }

        // Des données historiques ont pu être importées avant l'ajout de la
        // contrainte. Une référence absente ou appartenant à une autre commande
        // doit redevenir nullable, conformément au schéma de shipments.
        DB::table('shipments')
            ->whereNotNull('shipments.order_item_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('order_items')
                    ->whereColumn('order_items.id', 'shipments.order_item_id')
                    ->whereColumn('order_items.order_id', 'shipments.order_id');
            })
            ->update([
                'order_item_id' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Réparation de données irréversible : aucune référence invalide
        // ne doit être recréée.
    }
};
