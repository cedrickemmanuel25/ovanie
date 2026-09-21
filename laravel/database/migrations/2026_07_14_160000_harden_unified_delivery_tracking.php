<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_locations')) {
            $needsAssignment = ! Schema::hasColumn('driver_locations', 'delivery_assignment_id');
            $needsMission = ! Schema::hasColumn('driver_locations', 'mission_number');
            $needsOrder = ! Schema::hasColumn('driver_locations', 'order_id');

            if ($needsAssignment || $needsMission || $needsOrder) {
                Schema::table('driver_locations', function (Blueprint $table) use ($needsAssignment, $needsMission, $needsOrder) {
                    if ($needsAssignment) {
                        $table->unsignedBigInteger('delivery_assignment_id')->nullable()->index()->after('driver_id');
                    }
                    if ($needsMission) {
                        $table->string('mission_number', 80)->nullable()->index()->after('delivery_assignment_id');
                    }
                    if ($needsOrder) {
                        $table->unsignedBigInteger('order_id')->nullable()->index()->after('shipment_id');
                    }
                });
            }

            // Les anciennes positions liées à une expédition récupèrent leur commande.
            if (Schema::hasTable('shipments') && Schema::hasColumn('driver_locations', 'order_id')) {
                DB::table('driver_locations')
                    ->whereNull('order_id')
                    ->whereNotNull('shipment_id')
                    ->orderBy('id')
                    ->chunkById(500, function ($locations) {
                        foreach ($locations as $location) {
                            $orderId = DB::table('shipments')->where('id', $location->shipment_id)->value('order_id');
                            if ($orderId) {
                                DB::table('driver_locations')->where('id', $location->id)->update(['order_id' => $orderId]);
                            }
                        }
                    });
            }
        }

        if (Schema::hasTable('delivery_assignments')
            && ! Schema::hasColumn('delivery_assignments', 'last_manual_status_at')) {
            Schema::table('delivery_assignments', function (Blueprint $table) {
                $table->timestamp('last_manual_status_at')->nullable()->index()->after('manual_eta_at');
            });
        }
    }

    public function down(): void
    {
        // Aucun rollback destructif : les positions GPS constituent un historique opérationnel.
    }
};
