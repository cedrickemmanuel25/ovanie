<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seller_delivery_zones')
            && ! Schema::hasColumn('seller_delivery_zones', 'vehicle_code')) {
            Schema::table('seller_delivery_zones', function (Blueprint $table) {
                $table->string('vehicle_code', 30)->nullable()->after('coverage_type')->index();
            });
        }

        if (! Schema::hasTable('seller_delivery_zones')
            || ! Schema::hasColumn('seller_delivery_zones', 'vehicle_code')) {
            return;
        }

        $rows = DB::table('seller_delivery_zones')
            ->select(['id', 'shop_id', 'city', 'commune', 'max_weight_kg', 'vehicle_code'])
            ->get();

        $groupCounts = $rows->groupBy(function ($row) {
            return implode('|', [
                (int) $row->shop_id,
                mb_strtolower(trim((string) $row->city)),
                mb_strtolower(trim((string) $row->commune)),
            ]);
        })->map->count();

        foreach ($rows as $row) {
            if (filled($row->vehicle_code)) {
                continue;
            }

            $groupKey = implode('|', [
                (int) $row->shop_id,
                mb_strtolower(trim((string) $row->city)),
                mb_strtolower(trim((string) $row->commune)),
            ]);

            $maxWeight = $row->max_weight_kg !== null ? (float) $row->max_weight_kg : null;
            $vehicleCode = match (true) {
                $maxWeight !== null && $maxWeight <= 20 => 'moto',
                $maxWeight !== null && $maxWeight <= 250 => 'tricycle',
                $maxWeight !== null && $maxWeight <= 1000 => 'pickup',
                $maxWeight !== null && $maxWeight <= 3000 => 'camion_3t',
                $maxWeight !== null && $maxWeight > 3000 => 'camion_10t',
                ($groupCounts[$groupKey] ?? 0) > 1 => 'camion_10t',
                default => null, // Ancien tarif unique par commune : reste générique.
            };

            if ($vehicleCode !== null) {
                DB::table('seller_delivery_zones')
                    ->where('id', $row->id)
                    ->update([
                        'vehicle_code' => $vehicleCode,
                        'updated_at' => now(),
                    ]);
            }
        }

        // Les profils créés par l'ancien seed possédaient des zones mais pas
        // toujours les limites globales exigées par le validateur.
        if (Schema::hasTable('seller_delivery_profiles')) {
            DB::table('seller_delivery_profiles')
                ->whereIn('shop_id', DB::table('seller_delivery_zones')->select('shop_id')->distinct())
                ->whereNull('max_weight_kg')
                ->update(['max_weight_kg' => 10000, 'updated_at' => now()]);

            DB::table('seller_delivery_profiles')
                ->whereIn('shop_id', DB::table('seller_delivery_zones')->select('shop_id')->distinct())
                ->whereNull('max_volume_m3')
                ->update(['max_volume_m3' => 60, 'updated_at' => now()]);

            DB::table('seller_delivery_profiles')
                ->whereIn('shop_id', DB::table('seller_delivery_zones')->select('shop_id')->distinct())
                ->where(function ($query) {
                    $query->whereNull('conditions')->orWhere('conditions', '');
                })
                ->update([
                    'conditions' => 'Livraison selon la commune, le véhicule requis, le poids et le volume de la commande.',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seller_delivery_zones')
            && Schema::hasColumn('seller_delivery_zones', 'vehicle_code')) {
            Schema::table('seller_delivery_zones', function (Blueprint $table) {
                $table->dropIndex(['vehicle_code']);
                $table->dropColumn('vehicle_code');
            });
        }
    }
};
