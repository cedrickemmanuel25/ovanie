<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('shipments', 'order_item_id')) {
                    $table->unsignedBigInteger('order_item_id')->nullable()->index()->after('order_id');
                }

                if (! Schema::hasColumn('shipments', 'shop_id')) {
                    $table->unsignedBigInteger('shop_id')->nullable()->index()->after('order_item_id');
                }

                if (! Schema::hasColumn('shipments', 'delivery_service_id')) {
                    $table->unsignedBigInteger('delivery_service_id')->nullable()->index()->after('shop_id');
                }

                if (! Schema::hasColumn('shipments', 'meta')) {
                    $table->json('meta')->nullable();
                }
            });
        }

        if (! Schema::hasTable('shipment_status_histories')) {
            Schema::create('shipment_status_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shipment_id')->index();
                $table->string('status', 80)->index();
                $table->string('label')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('shipments') && Schema::hasTable('order_items')) {
            // Marque comme OVANIE les expéditions déjà liées à une ligne OVANIE mais dont le meta n'a pas été renseigné.
            $rows = DB::table('shipments')
                ->join('order_items', 'order_items.id', '=', 'shipments.order_item_id')
                ->where('order_items.delivery_provider', 'ovanie')
                ->select('shipments.id', 'shipments.meta')
                ->get();

            foreach ($rows as $row) {
                $meta = json_decode($row->meta ?: '{}', true) ?: [];
                $meta['provider_type'] = 'ovanie';
                DB::table('shipments')->where('id', $row->id)->update([
                    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Migration volontairement safe : on ne supprime pas les données de livraison.
    }
};
