<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_address')) {
                $table->text('delivery_address')->nullable();
            }

            if (! Schema::hasColumn('orders', 'delivery_lat')) {
                $table->decimal('delivery_lat', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('orders', 'delivery_lng')) {
                $table->decimal('delivery_lng', 11, 7)->nullable();
            }
        });

        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('shops', 'longitude')) {
                $table->decimal('longitude', 11, 7)->nullable();
            }

            if (! Schema::hasColumn('shops', 'logistics_type')) {
                $table->string('logistics_type', 30)->default('seller');
            }
        });

        // Synchronisation douce des anciennes commandes : si delivery_address est vide,
        // on reprend l'adresse principale enregistrée au checkout.
        if (Schema::hasColumn('orders', 'delivery_address') && Schema::hasColumn('orders', 'address')) {
            DB::table('orders')
                ->whereNull('delivery_address')
                ->whereNotNull('address')
                ->update(['delivery_address' => DB::raw('address')]);
        }

        // Compatibilité si une ancienne version avait créé delivery_latitude / delivery_longitude.
        if (Schema::hasColumn('orders', 'delivery_latitude') && Schema::hasColumn('orders', 'delivery_lat')) {
            DB::statement('UPDATE orders SET delivery_lat = delivery_latitude WHERE delivery_lat IS NULL AND delivery_latitude IS NOT NULL');
        }

        if (Schema::hasColumn('orders', 'delivery_longitude') && Schema::hasColumn('orders', 'delivery_lng')) {
            DB::statement('UPDATE orders SET delivery_lng = delivery_longitude WHERE delivery_lng IS NULL AND delivery_longitude IS NOT NULL');
        }
    }

    public function down(): void
    {
        // Migration volontairement non destructive.
    }
};
