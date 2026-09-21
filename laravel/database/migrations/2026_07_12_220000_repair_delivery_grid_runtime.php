<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const VEHICLES = ['moto', 'tricycle', 'pickup', 'camion_3t', 'camion_10t'];

    public function up(): void
    {
        $this->ensureSellerVehicleColumn();
        $this->repairSellerZones();
        $this->repairSellerProfiles();
        $this->repairProductMetrics();
        $this->normalizeOvanieGridCodes();
    }

    public function down(): void
    {
        // Migration de réparation non destructive.
    }

    private function ensureSellerVehicleColumn(): void
    {
        if (Schema::hasTable('seller_delivery_zones')
            && ! Schema::hasColumn('seller_delivery_zones', 'vehicle_code')) {
            Schema::table('seller_delivery_zones', function (Blueprint $table) {
                $table->string('vehicle_code', 30)->nullable()->after('coverage_type')->index();
            });
        }
    }

    private function repairSellerZones(): void
    {
        if (! Schema::hasTable('seller_delivery_zones')
            || ! Schema::hasColumn('seller_delivery_zones', 'vehicle_code')) {
            return;
        }

        $rows = DB::table('seller_delivery_zones')
            ->select([
                'id', 'shop_id', 'city', 'commune', 'vehicle_code',
                'delivery_price', 'max_weight_kg', 'max_volume_m3',
            ])
            ->orderBy('shop_id')
            ->orderBy('city')
            ->orderBy('commune')
            ->orderBy('delivery_price')
            ->get();

        foreach ($rows as $row) {
            $normalized = $this->normalizeVehicleCode($row->vehicle_code);

            if ($normalized !== null && $normalized !== $row->vehicle_code) {
                DB::table('seller_delivery_zones')->where('id', $row->id)->update([
                    'vehicle_code' => $normalized,
                    'updated_at' => now(),
                ]);
            }
        }

        $groups = $rows->groupBy(fn ($row) => implode('|', [
            (int) $row->shop_id,
            Str::slug((string) $row->city),
            Str::slug((string) $row->commune),
        ]));

        foreach ($groups as $group) {
            $ordered = $group->sortBy(fn ($row) => (float) $row->delivery_price)->values();
            $hasFiveTariffs = $ordered->count() >= 5;

            foreach ($ordered as $index => $row) {
                if ($this->normalizeVehicleCode($row->vehicle_code) !== null) {
                    continue;
                }

                $vehicleCode = $this->vehicleFromCapacity(
                    $row->max_weight_kg,
                    $row->max_volume_m3
                );

                // Les anciennes grilles avaient cinq lignes par commune mais
                // aucune colonne véhicule. Dans ce cas, l'ordre croissant des
                // tarifs correspond à moto → camion 10T.
                if ($vehicleCode === null && $hasFiveTariffs && isset(self::VEHICLES[$index])) {
                    $vehicleCode = self::VEHICLES[$index];
                }

                if ($vehicleCode === null) {
                    continue; // Ancien tarif unique : reste un tarif générique.
                }

                DB::table('seller_delivery_zones')->where('id', $row->id)->update([
                    'vehicle_code' => $vehicleCode,
                    'max_weight_kg' => $this->capacity($vehicleCode)['weight'],
                    'max_volume_m3' => $this->capacity($vehicleCode)['volume'],
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function repairSellerProfiles(): void
    {
        if (! Schema::hasTable('seller_delivery_profiles')
            || ! Schema::hasTable('seller_delivery_zones')) {
            return;
        }

        $shopIds = DB::table('seller_delivery_zones')
            ->where('is_active', true)
            ->distinct()
            ->pluck('shop_id');

        foreach ($shopIds as $shopId) {
            $profile = DB::table('seller_delivery_profiles')->where('shop_id', $shopId)->first();

            if (! $profile) {
                continue;
            }

            $vehicles = DB::table('seller_delivery_zones')
                ->where('shop_id', $shopId)
                ->where('is_active', true)
                ->pluck('vehicle_code')
                ->map(fn ($code) => $this->normalizeVehicleCode($code))
                ->filter()
                ->unique()
                ->values()
                ->all();

            DB::table('seller_delivery_profiles')->where('id', $profile->id)->update([
                'is_enabled' => true,
                'default_delay' => filled($profile->default_delay) ? $profile->default_delay : '24h',
                'max_weight_kg' => ((float) ($profile->max_weight_kg ?? 0)) > 0 ? $profile->max_weight_kg : 10000,
                'max_volume_m3' => ((float) ($profile->max_volume_m3 ?? 0)) > 0 ? $profile->max_volume_m3 : 60,
                'vehicle_types' => json_encode($vehicles ?: self::VEHICLES, JSON_UNESCAPED_UNICODE),
                'conditions' => filled($profile->conditions)
                    ? $profile->conditions
                    : 'Livraison calculée selon la commune, le véhicule requis, le poids et le volume de la commande.',
                'updated_at' => now(),
            ]);
        }
    }

    private function repairProductMetrics(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $hasWeight = Schema::hasColumn('products', 'weight');
        $hasWeightKg = Schema::hasColumn('products', 'weight_kg');
        $hasVolume = Schema::hasColumn('products', 'volume_m3');
        $hasDimensions = Schema::hasColumn('products', 'length_cm')
            && Schema::hasColumn('products', 'width_cm')
            && Schema::hasColumn('products', 'height_cm');

        if (! $hasWeightKg && ! $hasVolume) {
            return;
        }

        DB::table('products')
            ->select(array_values(array_filter([
                'id',
                $hasWeight ? 'weight' : null,
                $hasWeightKg ? 'weight_kg' : null,
                $hasVolume ? 'volume_m3' : null,
                $hasDimensions ? 'length_cm' : null,
                $hasDimensions ? 'width_cm' : null,
                $hasDimensions ? 'height_cm' : null,
            ])))
            ->orderBy('id')
            ->chunkById(250, function ($products) use ($hasWeight, $hasWeightKg, $hasVolume, $hasDimensions) {
                foreach ($products as $product) {
                    $updates = [];

                    if ($hasWeightKg
                        && (float) ($product->weight_kg ?? 0) <= 0
                        && $hasWeight
                        && (float) ($product->weight ?? 0) > 0) {
                        $updates['weight_kg'] = (float) $product->weight;
                    }

                    if ($hasVolume
                        && (float) ($product->volume_m3 ?? 0) <= 0
                        && $hasDimensions
                        && (float) ($product->length_cm ?? 0) > 0
                        && (float) ($product->width_cm ?? 0) > 0
                        && (float) ($product->height_cm ?? 0) > 0) {
                        $updates['volume_m3'] = round(
                            ((float) $product->length_cm
                                * (float) $product->width_cm
                                * (float) $product->height_cm) / 1000000,
                            4
                        );
                    }

                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('products')->where('id', $product->id)->update($updates);
                    }
                }
            }, 'id');
    }

    private function normalizeOvanieGridCodes(): void
    {
        if (Schema::hasTable('delivery_vehicle_rate_cards')) {
            $cards = DB::table('delivery_vehicle_rate_cards')->select('id', 'vehicle_code')->get();

            foreach ($cards as $card) {
                $normalized = $this->normalizeVehicleCode($card->vehicle_code);
                if ($normalized && $normalized !== $card->vehicle_code) {
                    DB::table('delivery_vehicle_rate_cards')->where('id', $card->id)->update([
                        'vehicle_code' => $normalized,
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (! Schema::hasTable('delivery_commune_rate_rules')) {
            return;
        }

        $rules = DB::table('delivery_commune_rate_rules')->select('id', 'destination_commune', 'meta')->get();

        foreach ($rules as $rule) {
            $meta = is_string($rule->meta) ? json_decode($rule->meta, true) : (array) $rule->meta;

            if (! is_array($meta) || ! ($meta['ovanie_tariff'] ?? false)) {
                continue;
            }

            $vehicleCode = $this->normalizeVehicleCode($meta['vehicle_code'] ?? null);
            if ($vehicleCode) {
                $meta['vehicle_code'] = $vehicleCode;
            }

            DB::table('delivery_commune_rate_rules')->where('id', $rule->id)->update([
                'destination_commune' => Str::slug((string) $rule->destination_commune),
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }
    }

    private function normalizeVehicleCode(mixed $code): ?string
    {
        $code = Str::slug((string) $code, '_');

        return match ($code) {
            'moto', 'motorcycle' => 'moto',
            'tricycle', 'triporteur' => 'tricycle',
            'pickup', 'pick_up' => 'pickup',
            'camion3t', 'camion_3t', 'truck_3t' => 'camion_3t',
            'camion10t', 'camion_10t', 'truck_10t' => 'camion_10t',
            default => null,
        };
    }

    private function vehicleFromCapacity(mixed $weight, mixed $volume): ?string
    {
        $weight = $weight !== null ? (float) $weight : null;
        $volume = $volume !== null ? (float) $volume : null;

        return match (true) {
            $weight !== null && $weight > 0 && $weight <= 20 => 'moto',
            $weight !== null && $weight <= 250 => 'tricycle',
            $weight !== null && $weight <= 1000 => 'pickup',
            $weight !== null && $weight <= 3000 => 'camion_3t',
            $weight !== null && $weight > 3000 => 'camion_10t',
            $weight === null && $volume !== null && $volume <= 0.18 => 'moto',
            $weight === null && $volume !== null && $volume <= 1.5 => 'tricycle',
            $weight === null && $volume !== null && $volume <= 7 => 'pickup',
            $weight === null && $volume !== null && $volume <= 20 => 'camion_3t',
            $weight === null && $volume !== null && $volume > 20 => 'camion_10t',
            default => null,
        };
    }

    private function capacity(string $vehicleCode): array
    {
        return match ($vehicleCode) {
            'moto' => ['weight' => 20.0, 'volume' => 0.18],
            'tricycle' => ['weight' => 250.0, 'volume' => 1.5],
            'pickup' => ['weight' => 1000.0, 'volume' => 7.0],
            'camion_3t' => ['weight' => 3000.0, 'volume' => 20.0],
            default => ['weight' => null, 'volume' => 60.0],
        };
    }
};
