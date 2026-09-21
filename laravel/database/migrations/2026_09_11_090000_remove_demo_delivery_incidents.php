<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_incidents')) {
            return;
        }

        DB::table('delivery_incidents')
            ->where(function ($query) {
                if (Schema::hasColumn('delivery_incidents', 'reported_by_type')) {
                    $query->where('reported_by_type', 'like', 'demo%');
                }

                if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'order_number')) {
                    $query->orWhereIn('order_id', function ($orders) {
                        $orders->select('id')
                            ->from('orders')
                            ->where('order_number', 'like', '%DEMO%');
                    });
                }

                if (Schema::hasTable('delivery_assignments') && Schema::hasColumn('delivery_assignments', 'mission_number')) {
                    $query->orWhereIn('order_item_id', function ($assignments) {
                        $assignments->select('order_item_id')
                            ->from('delivery_assignments')
                            ->where('mission_number', 'like', '%DEMO%');
                    });
                }

                if (Schema::hasTable('shipments') && Schema::hasColumn('shipments', 'tracking_number')) {
                    $query->orWhereIn('shipment_id', function ($shipments) {
                        $shipments->select('id')
                            ->from('shipments')
                            ->where('tracking_number', 'like', '%DEMO%');
                    });
                }
            })
            ->delete();
    }

    public function down(): void
    {
        // Les dossiers de démonstration supprimés ne sont volontairement pas recréés.
    }
};
