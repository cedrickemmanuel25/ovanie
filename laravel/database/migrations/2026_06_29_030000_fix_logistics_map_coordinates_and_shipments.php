<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'delivery_lat')) {
                    $table->decimal('delivery_lat', 10, 7)->nullable()->after('delivery_city');
                }

                if (! Schema::hasColumn('orders', 'delivery_lng')) {
                    $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
                }
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('shipments', 'order_item_id')) {
                    $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained('order_items')->nullOnDelete();
                }

                if (! Schema::hasColumn('shipments', 'shop_id')) {
                    $table->foreignId('shop_id')->nullable()->after('order_item_id')->constrained('shops')->nullOnDelete();
                }

                if (! Schema::hasColumn('shipments', 'delivery_service_id')) {
                    $table->foreignId('delivery_service_id')->nullable()->after('shop_id')->constrained('delivery_services')->nullOnDelete();
                }
            });
        }

        // Corrige les anciennes commandes test sans coordonnées complètes.
        // Ce sont des coordonnées approximatives par commune, uniquement pour éviter les points aléatoires.
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'delivery_lng') && Schema::hasColumn('orders', 'delivery_lat')) {
            $rows = [
                ['commune' => 'Cocody', 'lat' => 5.3717000, 'lng' => -3.9883000],
                ['commune' => 'Plateau', 'lat' => 5.3230000, 'lng' => -4.0210000],
                ['commune' => 'Marcory', 'lat' => 5.3027000, 'lng' => -3.9827000],
                ['commune' => 'Treichville', 'lat' => 5.2935000, 'lng' => -4.0140000],
                ['commune' => 'Yopougon', 'lat' => 5.3369000, 'lng' => -4.0891000],
                ['commune' => 'Abobo', 'lat' => 5.4353000, 'lng' => -4.0247000],
                ['commune' => 'Adjamé', 'lat' => 5.3650000, 'lng' => -4.0236000],
                ['commune' => 'Koumassi', 'lat' => 5.3006000, 'lng' => -3.9457000],
                ['commune' => 'Port-Bouët', 'lat' => 5.2524000, 'lng' => -3.9299000],
                ['commune' => 'Bingerville', 'lat' => 5.3558000, 'lng' => -3.8854000],
            ];

            foreach ($rows as $row) {
                DB::table('orders')
                    ->where('delivery_commune', $row['commune'])
                    ->where(function ($q) {
                        $q->whereNull('delivery_lat')->orWhereNull('delivery_lng');
                    })
                    ->update([
                        'delivery_lat' => DB::raw('COALESCE(delivery_lat, ' . $row['lat'] . ')'),
                        'delivery_lng' => DB::raw('COALESCE(delivery_lng, ' . $row['lng'] . ')'),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // On ne supprime pas delivery_lat / delivery_lng pour ne pas perdre les coordonnées client.
    }
};
